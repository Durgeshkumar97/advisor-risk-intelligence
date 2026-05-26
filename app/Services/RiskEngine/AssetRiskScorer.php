<?php

namespace App\Services\RiskEngine;

/**
 * AssetRiskScorer
 *
 * Scores individual assets using SEBI Risk-o-meter guidelines.
 * Returns a 0–100 score based on asset class + keyword adjustments.
 *
 * BASE SCORES (by asset type):
 *   Cash              →  5 (lowest risk)
 *   Bond / G-Sec      → 15
 *   Liquid/Overnight  → 20
 *   Balanced/Hybrid   → 35
 *   ETF               → 45
 *   Mutual Fund       → 50
 *   Stock (large cap) → 65
 *   Foreign / Intl    → 75
 *   Crypto            → 90 (highest risk)
 *
 * KEYWORD ADJUSTMENTS (regex on asset name):
 *   Small-cap / Mid-cap    +22  (higher volatility)
 *   Liquid / Overnight     -28  (liquid funds are defensive)
 *   Ultra-short            -22  (defensive)
 *   Emerging / Frontier    +18  (higher volatility)
 *   Negative duration      -15  (bond funds, low rate-risk)
 */
class AssetRiskScorer
{
    private const BASE_SCORES = [
        'cash'          => 5,
        'bond'          => 15,
        'etf'           => 45,
        'mutual_fund'   => 50,
        'commodity'     => 55,
        'stock'         => 65,
        'foreign_stock' => 75,
        'crypto'        => 90,
    ];

    private const KEYWORD_ADJUSTMENTS = [
        'liquid|overnight|money.market'    => -28,
        'ultra.short|short.duration'       => -22,
        'gilt|g.sec|government|debenture'  => -15,
        'small.cap|mid.cap|midcap'         => +22,
        'emerging|frontier|international'  => +18,
        'growth|aggressive'                => +12,
        'value'                            => -5,
        'dividend'                         => -8,
    ];

    public function score(string $assetType, string $name): float
    {
        $baseScore = $this->baseScore($assetType);
        $adjustment = $this->adjustmentFromName($name);

        return max(0, min(100, round($baseScore + $adjustment, 2)));
    }

    public function level(float $score): string
    {
        return match (true) {
            $score < 30 => 'LOW',
            $score < 65 => 'MEDIUM',
            default     => 'HIGH',
        };
    }

    private function baseScore(string $assetType): float
    {
        return (float) (self::BASE_SCORES[strtolower($assetType)] ?? 50);
    }

    private function adjustmentFromName(string $name): float
    {
        $lower = strtolower($name);
        $totalAdj = 0;

        foreach (self::KEYWORD_ADJUSTMENTS as $pattern => $adjustment) {
            if (preg_match("/{$pattern}/i", $lower)) {
                $totalAdj += $adjustment;
            }
        }

        return $totalAdj;
    }
}
