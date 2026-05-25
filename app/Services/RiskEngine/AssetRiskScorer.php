<?php

namespace App\Services\RiskEngine;

/**
 * AssetRiskScorer
 *
 * Assigns a 0–100 risk score to a single portfolio holding.
 *
 * Methodology (SEBI Risk-o-meter inspired):
 *   1. Base score by asset class
 *   2. Name-keyword adjustments for mutual funds & ETFs
 *   3. Returns the bounded result
 *
 * No external API calls — fully deterministic and offline-capable.
 */
class AssetRiskScorer
{
    /*
    |--------------------------------------------------------------------------
    | BASE RISK SCORES BY ASSET TYPE  (0 = risk-free, 100 = maximum risk)
    |--------------------------------------------------------------------------
    |
    | Based on SEBI's Risk-o-meter categorisation and Indian market data:
    |   cash/bond        → capital-preservation instruments
    |   ETF/MF           → market-linked, adjusted by name keywords
    |   stock            → direct equity, high volatility by default
    |   commodity        → macro + currency risk on top of price risk
    |   foreign_stock    → currency + regulatory risk added
    |   crypto           → near-maximum risk
    |
    */

    private const BASE_SCORES = [
        'cash'         => 5,
        'bond'         => 15,
        'etf'          => 35,
        'mutual_fund'  => 45,
        'commodity'    => 60,
        'stock'        => 65,
        'foreign_stock'=> 75,
        'crypto'       => 90,
    ];

    /*
    |--------------------------------------------------------------------------
    | KEYWORD ADJUSTMENTS  (applied to mutual_fund and etf only)
    |--------------------------------------------------------------------------
    |
    | Regex patterns matched case-insensitively against the holding name.
    | First match wins — patterns are ordered from most-specific to least.
    |
    */

    private const KEYWORD_ADJUSTMENTS = [
        // Very low risk — money market / arbitrage / overnight
        ['pattern' => '/overnight|liquid\s+fund|arbitrage|money\s+market/i',        'delta' => -28],
        // Low risk — short duration / floater / gilt
        ['pattern' => '/ultra.short|ultra short|short.term|floater|gilt|banking\s+&?\s*psu/i', 'delta' => -22],
        // Low-moderate — low duration / conservative hybrid
        ['pattern' => '/low\s+duration|conservative|credit\s+risk/i',               'delta' => -15],
        // Moderate — balanced / hybrid / multi asset
        ['pattern' => '/balanced|hybrid|multi.asset|equity\s+savings/i',            'delta' => -10],
        // Neutral/large cap — nifty 50, bluechip, large cap, index
        ['pattern' => '/nifty\s+50|nifty50|sensex|bluechip|blue.chip|large.cap|index\s+fund/i', 'delta' => -5],
        // Slightly elevated — flexi cap, focused, value, contra
        ['pattern' => '/flexi.cap|focused|value\s+fund|contra|dividend\s+yield/i',  'delta' => 5],
        // Higher — mid cap, dynamic
        ['pattern' => '/mid.cap|dynamic\s+bond/i',                                   'delta' => 12],
        // High — small cap, micro, sectoral, thematic
        ['pattern' => '/small.cap|micro.cap|sector|thematic|infra|pharma|technology|tech\s+fund|banking\s+fund/i', 'delta' => 22],
    ];

    /*
    |--------------------------------------------------------------------------
    | SCORE
    |--------------------------------------------------------------------------
    */

    /**
     * Return a 0–100 risk score for one holding.
     *
     * @param  string  $assetType  One of the keys in BASE_SCORES (or unknown)
     * @param  string  $name       Holding name / scheme name
     * @return float               Risk score 0.00–100.00
     */
    public function score(string $assetType, string $name): float
    {
        $assetType = strtolower(trim($assetType));

        // Default to stock risk if unknown type
        $base = self::BASE_SCORES[$assetType] ?? self::BASE_SCORES['stock'];

        // Only apply name-keyword adjustments for fund-type assets
        if (in_array($assetType, ['mutual_fund', 'etf'], true)) {
            $base = $this->applyKeywordAdjustments($base, $name);
        }

        return (float) max(0, min(100, $base));
    }

    /*
    |--------------------------------------------------------------------------
    | RISK LEVEL FROM SCORE
    |--------------------------------------------------------------------------
    */

    public function level(float $score): string
    {
        $low  = config('risk.low_threshold', 30);
        $high = config('risk.high_threshold', 70);

        return match (true) {
            $score < $low  => 'LOW',
            $score < $high => 'MEDIUM',
            default        => 'HIGH',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE
    |--------------------------------------------------------------------------
    */

    private function applyKeywordAdjustments(float $base, string $name): float
    {
        foreach (self::KEYWORD_ADJUSTMENTS as ['pattern' => $pattern, 'delta' => $delta]) {
            if (preg_match($pattern, $name)) {
                return $base + $delta;
            }
        }

        return $base;
    }
}
