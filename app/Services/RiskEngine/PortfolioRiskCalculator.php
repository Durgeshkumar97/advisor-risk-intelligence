<?php

namespace App\Services\RiskEngine;

use Illuminate\Support\Collection;

class PortfolioRiskCalculator
{
    private const W_COMPOSITION = 0.30;
    private const W_CONCENTRATION = 0.25;
    private const W_EQUITY_RATIO = 0.25;
    private const W_DRAWDOWN = 0.20;
    private const EQUITY_TYPES = ['stock', 'mutual_fund', 'etf', 'foreign_stock', 'crypto'];

    private function marketMultiplier(): float
    {
        $m = (float) config('risk.market_multiplier', 1.05);
        return max(0.80, min(1.30, $m));
    }

    public function calculate(Collection $assets): array
    {
        if ($assets->isEmpty()) {
            return $this->emptyResult();
        }

        $totalCurrentValue = (float) $assets->sum('current_value');
        $totalInvestedValue = (float) $assets->sum('invested_value');

        if ($totalCurrentValue <= 0) {
            return $this->emptyResult();
        }

        $compositionScore = 0.0;
        $assetScores = [];

        foreach ($assets as $asset) {
            $weight = (float) $asset->current_value / $totalCurrentValue;
            $score = (float) $asset->risk_score;
            $compositionScore += $score * $weight;
            $assetScores[] = $score;
        }

        $hhi = 0.0;
        foreach ($assets as $asset) {
            $share = (float) $asset->current_value / $totalCurrentValue;
            $hhi += $share * $share;
        }

        $n = $assets->count();
        $perfectHhi = ($n > 0) ? (1.0 / $n) : 0;
        $hhiNorm = max(0.0, $hhi - $perfectHhi);
        $hhiRange = max(0.001, 1.0 - $perfectHhi);
        $concentrationScore = min(100.0, ($hhiNorm / $hhiRange) * 100.0);

        $equityValue = (float) $assets
            ->filter(fn ($a) =>
                in_array($a->asset_type, self::EQUITY_TYPES, true) &&
                (float) $a->risk_score >= 25
            )
            ->sum('current_value');

        $equityRatio = $equityValue / $totalCurrentValue;
        $equityRatioScore = $equityRatio * 100.0;

        $drawdownPct = 0.0;
        $drawdownScore = 0.0;

        if ($totalInvestedValue > 0) {
            $pnl = $totalCurrentValue - $totalInvestedValue;
            $drawdownPct = ($pnl < 0) ? abs($pnl / $totalInvestedValue) * 100.0 : 0.0;
            $drawdownScore = min(100.0, $drawdownPct * (100.0 / 30.0));
        }

        $rawScore = (
            $compositionScore * self::W_COMPOSITION +
            $concentrationScore * self::W_CONCENTRATION +
            $equityRatioScore * self::W_EQUITY_RATIO +
            $drawdownScore * self::W_DRAWDOWN
        ) * $this->marketMultiplier();

        $finalScore = (float) max(0, min(100, round($rawScore, 2)));
        $volatility = $this->stdDev($assetScores);

        $riskFlags = $this->buildRiskFlags(
            concentrationScore: $concentrationScore,
            equityRatio: $equityRatio,
            drawdownPct: $drawdownPct,
            assetCount: $n,
            finalScore: $finalScore
        );

        $dominantType = $assets
            ->groupBy('asset_type')
            ->map(fn ($g) => $g->sum('current_value'))
            ->sort()
            ->reverse()
            ->keys()
            ->first() ?? 'unknown';

        $nextAction = $this->buildNextAction(finalScore: $finalScore, riskFlags: $riskFlags);

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
                'risk_level' => $this->level($finalScore),
                'source' => 'portfolio-risk-calculator-v1',
            ],
        ];
    }

    public function level(float $score): string
    {
        return match (true) {
            $score < 30 => 'LOW',
            $score < 65 => 'MEDIUM',
            default => 'HIGH',
        };
    }

    private function buildRiskFlags(float $concentrationScore, float $equityRatio, float $drawdownPct, int $assetCount, float $finalScore): array
    {
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

    private function buildNextAction(float $finalScore, array $riskFlags): string
    {
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
                'source' => 'portfolio-risk-calculator-v1',
                'risk_level' => 'NONE',
                'asset_count' => 0,
            ],
        ];
    }
}
