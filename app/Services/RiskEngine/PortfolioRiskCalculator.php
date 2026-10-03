<?php

namespace App\Services\RiskEngine;

use App\Models\RiskScore;
use Illuminate\Support\Collection;

/**
 * PortfolioRiskCalculator
 *
 * Calculates a multi-factor portfolio risk score (0–100) from
 * a collection of PortfolioAsset records.
 *
 * FORMULA
 * ═══════
 *   Final Score = (
 *       composition_score   × 0.30   ← weighted avg of per-asset scores
 *     + concentration_score × 0.25   ← Herfindahl-Hirschman Index
 *     + equity_ratio_score  × 0.25   ← % of portfolio in equity-class assets
 *     + drawdown_score      × 0.20   ← unrealised loss as a risk signal
 *   ) × market_multiplier
 *
 * OUTPUT  (array)
 * ───────
 *   score          float    0–100   final composite score
 *   volatility     float    0–100   cross-asset score std-deviation
 *   drawdown       float    pct     estimated drawdown (loss %)
 *   next_action    string           1-line advisor action
 *   risk_flags     array            specific issues flagged
 *   meta           array            full breakdown for storage in JSON
 */
class PortfolioRiskCalculator
{
    /*
    |--------------------------------------------------------------------------
    | FACTOR WEIGHTS
    |--------------------------------------------------------------------------
    */

    private const W_COMPOSITION = 0.30;

    private const W_CONCENTRATION = 0.25;

    private const W_EQUITY_RATIO = 0.25;

    private const W_DRAWDOWN = 0.20;

    /*
    |--------------------------------------------------------------------------
    | RISK FLAG THRESHOLDS
    |--------------------------------------------------------------------------
    |
    | Where each risk flag is raised (see buildRiskFlags). Public so that the
    | report's wording for a flag reads the same number the flag is raised on.
    |
    */

    /** Concentration score (normalised HHI, 0–100) above which HIGH_CONCENTRATION is raised. */
    public const HIGH_CONCENTRATION_ABOVE = 60;

    /** Concentration score above which MODERATE_CONCENTRATION is raised. */
    public const MODERATE_CONCENTRATION_ABOVE = 30;

    /** Equity share of portfolio value (0–1) above which EQUITY_HEAVY is raised. */
    public const EQUITY_HEAVY_ABOVE = 0.90;

    /** Equity share of portfolio value (0–1) below which UNDERWEIGHTED_EQUITY is raised. */
    public const UNDERWEIGHTED_EQUITY_BELOW = 0.10;

    /** Unrealised loss (% of known cost) above which SIGNIFICANT_DRAWDOWN is raised. */
    public const SIGNIFICANT_DRAWDOWN_ABOVE = 15;

    /** Unrealised loss (% of known cost) above which MODERATE_DRAWDOWN is raised. */
    public const MODERATE_DRAWDOWN_ABOVE = 7;

    /** Number of holdings below which LOW_DIVERSIFICATION is raised. */
    public const LOW_DIVERSIFICATION_BELOW = 4;

    /** Number of holdings above which OVER_DIVERSIFICATION is raised. */
    public const OVER_DIVERSIFICATION_ABOVE = 25;

    /** Final score above which ELEVATED_OVERALL_RISK is raised. */
    public const ELEVATED_OVERALL_RISK_ABOVE = 75;

    /*
    |--------------------------------------------------------------------------
    | EQUITY ASSET TYPES (used for equity-ratio factor)
    |--------------------------------------------------------------------------
    */

    private const EQUITY_TYPES = [
        'stock', 'mutual_fund', 'etf', 'foreign_stock', 'crypto',
    ];

    /*
    |--------------------------------------------------------------------------
    | MARKET MULTIPLIER  (configurable without code deploy)
    |--------------------------------------------------------------------------
    |
    | Set RISK_MARKET_MULTIPLIER in .env to override.
    | Range: 0.85 (calm market) → 1.25 (high-volatility market).
    | Default: 1.0 (neutral — see config/risk.php)
    |
    */

    /**
     * Resolve the multiplier for ONE calculate() call.
     *
     * Takes an explicit override when the caller has a market snapshot, and
     * falls back to config otherwise. ProcessPortfolioFile used to do this by
     * assigning to config() at runtime, which leaks: config is process-global,
     * so within a single queue:work process a later job with no snapshot
     * inherited the previous job's multiplier. Passing it per-call keeps the
     * value scoped to the calculation it belongs to.
     */
    private function resolveMarketMultiplier(?float $override): float
    {
        $m = $override ?? (float) config('risk.market_multiplier', 1.0);

        return max(0.80, min(1.30, $m));
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE
    |--------------------------------------------------------------------------
    */

    /**
     * @param  \Illuminate\Support\Collection  $assets  PortfolioAsset models
     *                                                  (must have current_value, invested_value,
     *                                                  asset_type, name, risk_score)
     * @param  ?float  $marketMultiplier  Multiplier from the caller's market
     *                                    snapshot, if it has one. Omit to use
     *                                    the configured default. Clamped to
     *                                    0.80–1.30 either way.
     */
    public function calculate(Collection $assets, ?float $marketMultiplier = null): array
    {
        $multiplier = $this->resolveMarketMultiplier($marketMultiplier);

        /*
        |----------------------------------------------------------------------
        | GUARD: empty portfolio
        |----------------------------------------------------------------------
        */

        if ($assets->isEmpty()) {
            return $this->emptyResult();
        }

        /*
        |----------------------------------------------------------------------
        | TOTALS
        |----------------------------------------------------------------------
        */

        $totalCurrentValue = (float) $assets->sum('current_value');

        // Unrealised loss is measured only over holdings whose cost is known
        // (invested_value is null when the file gave none). Comparing total
        // current value against a partial cost total would count the
        // unknown-cost holdings as pure profit and hide real losses.
        //
        // A holding valued at cost (meta.value_basis = 'cost': its source has
        // no market price, so its current value IS its cost) is left out for
        // the same reason from the other side: it would count as money that
        // has not moved and dilute the losses of everything else.
        $knownCostAssets = $assets->filter(
            fn ($a) => $a->invested_value !== null && ($a->meta['value_basis'] ?? 'market') !== 'cost'
        );
        $totalInvestedValue = (float) $knownCostAssets->sum('invested_value');
        $currentValueWithKnownCost = (float) $knownCostAssets->sum('current_value');

        // Avoid division by zero
        if ($totalCurrentValue <= 0) {
            return $this->emptyResult();
        }

        /*
        |----------------------------------------------------------------------
        | FACTOR 1 — COMPOSITION SCORE
        | Weighted average of per-asset risk scores, weighted by allocation %
        |----------------------------------------------------------------------
        */

        $compositionScore = 0.0;
        $assetScores = [];

        foreach ($assets as $asset) {
            $weight = (float) $asset->current_value / $totalCurrentValue;
            $score = (float) $asset->risk_score;   // already scored by AssetRiskScorer
            $compositionScore += $score * $weight;
            $assetScores[] = $score;
        }

        /*
        |----------------------------------------------------------------------
        | FACTOR 2 — CONCENTRATION RISK (Herfindahl-Hirschman Index)
        | HHI = Σ(share²)  where share = asset_value / total_value
        | HHI range: 1/n (perfect equal split) → 1.0 (all in one asset)
        | We map: HHI = 0.0 → score 0 | HHI = 1.0 → score 100
        |----------------------------------------------------------------------
        */

        $hhi = 0.0;
        foreach ($assets as $asset) {
            $share = (float) $asset->current_value / $totalCurrentValue;
            $hhi += $share * $share;
        }

        // Normalise: perfect diversification baseline ≈ 1/n
        $n = $assets->count();
        $perfectHhi = ($n > 0) ? (1.0 / $n) : 0;
        $hhiNorm = max(0.0, $hhi - $perfectHhi);          // remove "expected" HHI
        $hhiRange = max(0.001, 1.0 - $perfectHhi);         // max possible excess HHI
        $concentrationScore = min(100.0, ($hhiNorm / $hhiRange) * 100.0);

        /*
        |----------------------------------------------------------------------
        | FACTOR 3 — EQUITY RATIO SCORE
        | % of portfolio value in equity-class assets → direct score 0–100
        |----------------------------------------------------------------------
        */

        // Only count equity-type assets whose risk_score is >= 25.
        // This excludes liquid / money-market / overnight funds that are
        // technically of type mutual_fund but behave as defensive instruments.
        $equityValue = (float) $assets
            ->filter(fn ($a) => in_array($a->asset_type, self::EQUITY_TYPES, true)
                && (float) $a->risk_score >= 25
            )
            ->sum('current_value');

        $equityRatio = $equityValue / $totalCurrentValue;      // 0.0–1.0
        $equityRatioScore = $equityRatio * 100.0;

        /*
        |----------------------------------------------------------------------
        | FACTOR 4 — DRAWDOWN SCORE
        | Existing unrealised loss as a risk signal.
        | Loss % of 0  → score 0 | Loss % of 30+ → score 100
        |----------------------------------------------------------------------
        */

        $drawdownPct = 0.0;
        $drawdownScore = 0.0;

        if ($totalInvestedValue > 0) {
            $pnl = $currentValueWithKnownCost - $totalInvestedValue;
            $drawdownPct = ($pnl < 0)
                ? abs($pnl / $totalInvestedValue) * 100.0   // positive percentage loss
                : 0.0;
            $drawdownScore = min(100.0, $drawdownPct * (100.0 / 30.0)); // 30% loss = 100 score
        }

        /*
        |----------------------------------------------------------------------
        | COMPOSITE SCORE
        |----------------------------------------------------------------------
        */

        $rawScore = (
            $compositionScore * self::W_COMPOSITION +
            $concentrationScore * self::W_CONCENTRATION +
            $equityRatioScore * self::W_EQUITY_RATIO +
            $drawdownScore * self::W_DRAWDOWN
        ) * $multiplier;

        $finalScore = (float) max(0, min(100, round($rawScore, 2)));

        /*
        |----------------------------------------------------------------------
        | VOLATILITY  (std-deviation of per-asset risk scores)
        | Measures how heterogeneous the portfolio risk profile is.
        |----------------------------------------------------------------------
        */

        $volatility = $this->stdDev($assetScores);

        /*
        |----------------------------------------------------------------------
        | RISK FLAGS
        |----------------------------------------------------------------------
        */

        $riskFlags = $this->buildRiskFlags(
            concentrationScore: $concentrationScore,
            equityRatio: $equityRatio,
            drawdownPct: $drawdownPct,
            assetCount: $n,
            finalScore: $finalScore
        );

        /*
        |----------------------------------------------------------------------
        | DOMINANT ASSET TYPE
        |----------------------------------------------------------------------
        */

        $dominantType = $assets
            ->groupBy('asset_type')
            ->map(fn ($g) => $g->sum('current_value'))
            ->sort()
            ->reverse()
            ->keys()
            ->first() ?? 'unknown';

        /*
        |----------------------------------------------------------------------
        | STOCK RISK DATA QUALITY  (informational only — does not affect score)
        |----------------------------------------------------------------------
        |
        | Count of holdings whose per-asset score fell back to AssetRiskScorer's
        | static default because a live StockRiskService classification wasn't
        | available (see AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE and
        | ProcessPortfolioFile's meta.stock_risk write). Read-only aggregation
        | over $asset->meta — does not change composition/concentration/
        | equity-ratio/drawdown or the composite score itself.
        |
        */

        $stockRiskFallbackCount = $assets
            ->filter(fn ($a) => ($a->meta['stock_risk']['source'] ?? null) === AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE)
            ->count();

        /*
        |----------------------------------------------------------------------
        | NEXT ACTION
        |----------------------------------------------------------------------
        */

        $nextAction = $this->buildNextAction(
            finalScore: $finalScore,
            riskFlags: $riskFlags
        );

        /*
        |----------------------------------------------------------------------
        | RETURN
        |----------------------------------------------------------------------
        */

        return [
            'score' => $finalScore,
            'volatility' => round($volatility, 2),
            'drawdown' => round($drawdownPct, 2),
            'next_action' => $nextAction,
            'risk_flags' => $riskFlags,
            'meta' => [
                'composition_score' => round($compositionScore, 2),
                'concentration_score' => round($concentrationScore, 2),
                'equity_ratio_score' => round($equityRatioScore, 2),
                'drawdown_score' => round($drawdownScore, 2),
                'equity_ratio_pct' => round($equityRatio * 100, 1),
                'hhi' => round($hhi, 4),
                'asset_count' => $n,
                'dominant_asset_type' => $dominantType,
                'total_invested' => round($totalInvestedValue, 2),
                'total_current' => round($totalCurrentValue, 2),
                'market_multiplier' => $multiplier,
                'risk_flags' => $riskFlags,
                'risk_level' => $this->level($finalScore),
                'stock_risk_fallback_count' => $stockRiskFallbackCount,
                // Renamed from 'source' to avoid colliding with the unrelated
                // per-asset AssetRiskScorer::SOURCE_* provenance label stored
                // in PortfolioAsset.meta.stock_risk.source — this key is a
                // version tag for this calculator, not data provenance.
                'calculator_version' => 'portfolio-risk-calculator-v1',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LEVEL HELPER
    |--------------------------------------------------------------------------
    */

    public function level(float $score): string
    {
        return RiskScore::levelFromScore($score);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildRiskFlags(
        float $concentrationScore,
        float $equityRatio,
        float $drawdownPct,
        int $assetCount,
        float $finalScore
    ): array {
        $flags = [];

        if ($concentrationScore > self::HIGH_CONCENTRATION_ABOVE) {
            $flags[] = 'HIGH_CONCENTRATION';
        } elseif ($concentrationScore > self::MODERATE_CONCENTRATION_ABOVE) {
            $flags[] = 'MODERATE_CONCENTRATION';
        }

        if ($equityRatio > self::EQUITY_HEAVY_ABOVE) {
            $flags[] = 'EQUITY_HEAVY';
        } elseif ($equityRatio < self::UNDERWEIGHTED_EQUITY_BELOW) {
            $flags[] = 'UNDERWEIGHTED_EQUITY';
        }

        if ($drawdownPct > self::SIGNIFICANT_DRAWDOWN_ABOVE) {
            $flags[] = 'SIGNIFICANT_DRAWDOWN';
        } elseif ($drawdownPct > self::MODERATE_DRAWDOWN_ABOVE) {
            $flags[] = 'MODERATE_DRAWDOWN';
        }

        if ($assetCount < self::LOW_DIVERSIFICATION_BELOW) {
            $flags[] = 'LOW_DIVERSIFICATION';
        }

        if ($assetCount > self::OVER_DIVERSIFICATION_ABOVE) {
            $flags[] = 'OVER_DIVERSIFICATION';
        }

        if ($finalScore > self::ELEVATED_OVERALL_RISK_ABOVE) {
            $flags[] = 'ELEVATED_OVERALL_RISK';
        }

        return $flags;
    }

    /**
     * A neutral, descriptive statement of what the numbers show — deliberately
     * NOT a recommendation.
     *
     * This string is surfaced in the client-facing PDF (under "Observations")
     * and its covering email. Prescriptive allocation language there would read
     * as investment advice, which RiskSignal does not provide and is not
     * registered to provide (see resources/views/legal/terms.blade.php). Keep
     * every branch observational: state the condition, never prescribe an
     * action. No "should", "consider", "recommend", "review", or "discuss".
     */
    private function buildNextAction(
        float $finalScore,
        array $riskFlags
    ): string {
        // Priority: the most material condition is the one worth stating

        if (in_array('SIGNIFICANT_DRAWDOWN', $riskFlags)) {
            return 'The portfolio carries unrealised losses across a significant share of holdings.';
        }

        if (in_array('HIGH_CONCENTRATION', $riskFlags) && $finalScore > 65) {
            return 'Holdings are concentrated in a small number of positions, and overall risk is elevated.';
        }

        if ($finalScore > 75) {
            return 'Overall risk sits in the elevated band relative to the portfolio\'s composition and exposure.';
        }

        if (in_array('EQUITY_HEAVY', $riskFlags)) {
            return 'Equity allocation is very high, with limited debt or hybrid exposure.';
        }

        if (in_array('MODERATE_DRAWDOWN', $riskFlags)) {
            return 'Some positions are currently valued below their purchase price.';
        }

        if (in_array('MODERATE_CONCENTRATION', $riskFlags)) {
            return 'Holdings show moderate concentration across a limited number of positions.';
        }

        if ($finalScore < (float) config('risk.low_threshold', 30)) {
            return 'Overall risk sits in the low band, and the current allocation appears balanced.';
        }

        return 'Overall risk sits within acceptable risk parameters.';
    }

    private function stdDev(array $scores): float
    {
        $n = count($scores);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($scores) / $n;
        $sumSq = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $scores));

        return sqrt($sumSq / ($n - 1));
    }

    private function emptyResult(): array
    {
        return [
            'score' => 0.0,
            'volatility' => 0.0,
            'drawdown' => 0.0,
            'next_action' => 'Upload a portfolio file to receive your personalised risk signal.',
            'risk_flags' => ['NO_HOLDINGS'],
            'meta' => [
                'calculator_version' => 'portfolio-risk-calculator-v1',
                'risk_level' => 'NONE',
                'asset_count' => 0,
                'stock_risk_fallback_count' => 0,
            ],
        ];
    }
}
