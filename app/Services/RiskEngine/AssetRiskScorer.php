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
 *   3. For 'stock' holdings, an optional pre-fetched StockRiskService
 *      classification overrides the flat base score (see $stockRisk below)
 *   4. Returns the bounded result
 *
 * No external API calls of its own — fully deterministic and offline-capable.
 * Stock-specific data must be fetched by the caller (batched, before any DB
 * transaction opens — see ProcessPortfolioFile) and passed in per asset;
 * this class never calls StockRiskService directly, so calling score() in a
 * per-asset loop never fires one HTTP request per holding.
 */
class AssetRiskScorer
{
    /*
    |--------------------------------------------------------------------------
    | STOCK RISK LEVEL → SCORE  (only used when live classifier data is given)
    |--------------------------------------------------------------------------
    |
    | Anchored to the classifier's empirical NIFTY50 volatility percentile
    | bands (risk_service/app/config.py): Medium (25-80th percentile, the
    | typical case) is kept at today's flat default of 65 so an average
    | stock's score doesn't shift on rollout. Low/High are a symmetric ±15
    | step reflecting that Low (~29th percentile) is still equity risk, not
    | "safe", while High (~top 20th percentile) is a confirmed statistical
    | outlier and deliberately scores above foreign_stock's flat, unverified
    | 75 — a data-backed signal outranks a heuristic category default.
    |
    */

    private const STOCK_RISK_LEVEL_SCORES = [
        'Low'    => 50,
        'Medium' => 65,
        'High'   => 80,
    ];

    /*
    |--------------------------------------------------------------------------
    | SCORE SOURCE LABELS  (auditable provenance for meta.stock_risk.source)
    |--------------------------------------------------------------------------
    */

    public const SOURCE_LIVE                = 'live';
    public const SOURCE_FALLBACK_UNAVAILABLE = 'fallback_unavailable';
    public const SOURCE_CATEGORY_DEFAULT     = 'category_default';

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
     * Return a 0–100 risk score for one holding, plus its provenance.
     *
     * @param  string  $assetType  One of the keys in BASE_SCORES (or unknown)
     * @param  string  $name       Holding name / scheme name
     * @param  ?array  $stockRisk  Pre-fetched StockRiskService result for this
     *                             symbol (see StockRiskService::classifyBatch()),
     *                             or null. Only consulted when $assetType is
     *                             'stock'; ignored for every other asset type.
     *                             Expected shape: ['risk_level' => ?string,
     *                             'unavailable' => bool, ...]. This method never
     *                             calls StockRiskService itself — the caller
     *                             must batch-fetch and pass results in per asset.
     * @return array{score: float, source: string}  source is one of
     *                             self::SOURCE_LIVE, SOURCE_FALLBACK_UNAVAILABLE,
     *                             or SOURCE_CATEGORY_DEFAULT.
     */
    public function score(string $assetType, string $name, ?array $stockRisk = null): array
    {
        $assetType = strtolower(trim($assetType));

        if ($assetType === 'stock') {
            $riskLevel   = $stockRisk['risk_level'] ?? null;
            $unavailable = (bool) ($stockRisk['unavailable'] ?? true);

            // `stale` is a caveat flag, not a failure — a stale-but-valid
            // classification is still the best available data and is used
            // as live. Only unavailable (or an unrecognized risk_level —
            // never coerce garbage into a real score) falls back.
            if (!$unavailable && isset(self::STOCK_RISK_LEVEL_SCORES[$riskLevel])) {
                return [
                    'score'  => (float) self::STOCK_RISK_LEVEL_SCORES[$riskLevel],
                    'source' => self::SOURCE_LIVE,
                ];
            }

            return [
                'score'  => (float) self::BASE_SCORES['stock'],
                'source' => self::SOURCE_FALLBACK_UNAVAILABLE,
            ];
        }

        // Default to stock risk if unknown type
        $base = self::BASE_SCORES[$assetType] ?? self::BASE_SCORES['stock'];

        // Only apply name-keyword adjustments for fund-type assets
        if (in_array($assetType, ['mutual_fund', 'etf'], true)) {
            $base = $this->applyKeywordAdjustments($base, $name);
        }

        return [
            'score'  => (float) max(0, min(100, $base)),
            'source' => self::SOURCE_CATEGORY_DEFAULT,
        ];
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
