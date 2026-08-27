<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MARKET MULTIPLIER
    |--------------------------------------------------------------------------
    |
    | Scales every portfolio risk score up or down to reflect current
    | macro market conditions. Change this from your .env WITHOUT a deploy.
    |
    | Range: 0.80 (calm / bull market) → 1.30 (high-volatility / correction)
    | Default: 1.05 (slightly elevated — reflects normal Indian market noise)
    |
    | .env:  RISK_MARKET_MULTIPLIER=1.10
    |
    */

    'market_multiplier' => (float) env('RISK_MARKET_MULTIPLIER', 1.05),

    /*
    |--------------------------------------------------------------------------
    | RISK LEVEL THRESHOLDS
    |--------------------------------------------------------------------------
    |
    | These thresholds determine the risk level label from a numeric score.
    |   score <  low_threshold   → LOW
    |   score <  high_threshold  → MEDIUM
    |   score >= high_threshold  → HIGH
    |
    */

    'low_threshold' => (int) env('RISK_LOW_THRESHOLD', 30),
    'high_threshold' => (int) env('RISK_HIGH_THRESHOLD', 70),

    /*
    |--------------------------------------------------------------------------
    | MARKET RISK CSV PATH
    |--------------------------------------------------------------------------
    |
    | Source file for `market-risk:sync`. Defaults to a path inside the
    | application's own storage so the command is portable across machines —
    | the previous default was a developer's local absolute path, which could
    | never exist on the production host.
    |
    | Override per-environment:  MARKET_RISK_CSV_PATH=/absolute/path/to.csv
    |
    */

    'market_risk_csv_path' => env(
        'MARKET_RISK_CSV_PATH',
        storage_path('app/market-risk/nifty500_enriched.csv'),
    ),

];
