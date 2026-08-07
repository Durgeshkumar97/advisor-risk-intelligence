<?php

namespace App\Services\RiskEngine;

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
    | Default: 1.05 (slightly elevated — typical Indian market)
    |
    */

    private function marketMultiplier(): float
    {
        $m = (float) config('risk.market_multiplier', 1.05);

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
     */
    public function calculate(Collection $assets): array
    {
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
        $totalInvestedValue = (float) $assets->sum('invested_value');

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
            $pnl = $totalCurrentValue - $totalInvestedValue;
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
        ) * $this->marketMultiplier();

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
                'market_multiplier' => $this->marketMultiplier(),
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
        $low = config('risk.low_threshold', 30);
        $high = config('risk.high_threshold', 70);

        return match (true) {
            $score < $low => 'LOW',
            $score < $high => 'MEDIUM',
            default => 'HIGH',
        };
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

        if ($concentrationScore > 60) {
            $flags[] = 'HIGH_CONCENTRATION';
        } elseif ($concentrationScore > 30) {
            $flags[] = 'MODERATE_CONCENTRATION';
        }

        if ($equityRatio > 0.90) {
            $flags[] = 'EQUITY_HEAVY';
        } elseif ($equityRatio < 0.10) {
            $flags[] = 'UNDERWEIGHTED_EQUITY';
        }

        if ($drawdownPct > 15) {
            $flags[] = 'SIGNIFICANT_DRAWDOWN';
        } elseif ($drawdownPct > 7) {
            $flags[] = 'MODERATE_DRAWDOWN';
        }

        if ($assetCount < 4) {
            $flags[] = 'LOW_DIVERSIFICATION';
        }

        if ($assetCount > 25) {
            $flags[] = 'OVER_DIVERSIFICATION';
        }

        if ($finalScore > 75) {
            $flags[] = 'ELEVATED_OVERALL_RISK';
        }

        return $flags;
    }

    private function buildNextAction(
        float $finalScore,
        array $riskFlags
    ): string {
        // Priority: most urgent condition drives the action

        if (in_array('SIGNIFICANT_DRAWDOWN', $riskFlags)) {
            return 'Significant unrealised losses detected — review and discuss rebalancing with clients.';
        }

        if (in_array('HIGH_CONCENTRATION', $riskFlags) && $finalScore > 65) {
            return 'Portfolio is over-concentrated — discuss diversification across sectors and asset classes.';
        }

        if ($finalScore > 75) {
            return 'Risk is elevated — prioritise client conversations about exposure and risk tolerance.';
        }

        if (in_array('EQUITY_HEAVY', $riskFlags)) {
            return 'Equity allocation is very high — consider adding debt or hybrid funds for balance.';
        }

        if (in_array('MODERATE_DRAWDOWN', $riskFlags)) {
            return 'Some positions are underperforming — review laggards and assess strategic fit.';
        }

        if (in_array('MODERATE_CONCENTRATION', $riskFlags)) {
            return 'Moderate concentration detected — gradual diversification recommended.';
        }

        if ($finalScore < 30) {
            return 'Portfolio risk is low — maintain current allocation and stay the course.';
        }

        return 'Portfolio is within acceptable risk parameters — continue monitoring.';
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
