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
    | MARKET SNAPSHOT MAX AGE
    |--------------------------------------------------------------------------
    |
    | How many whole calendar days old (IST) a market_risk_snapshots row may be
    | and still have its multiplier applied. Past this, ProcessPortfolioFile
    | falls back to `market_multiplier` above, logs a warning, and the report
    | says the context is unavailable rather than printing a stale regime as
    | though it were current.
    |
    | Why 7, on the evidence as of 2026-09-23:
    |
    |   - The underlying market_risk_score is computed per NSE trading day, so
    |     the INTENDED cadence is daily. 7 tolerates a long weekend plus a
    |     couple of missed runs while a regime read is still informative.
    |   - The ACTUAL cadence is worse. The producer (FinAdvisorAI's
    |     Nifty500/weekly_update.py) is a manual local script: no workflow runs
    |     it, and it hardcodes a developer-machine path so it cannot run in CI.
    |     Its last emitted data row is 2026-08-17.
    |   - So until a transport and a scheduled producer exist, expect the stale
    |     path to be the normal path. That is the point: it replaces a silent
    |     wrong multiplier with a visible, dated statement of ignorance.
    |
    | .env:  MARKET_SNAPSHOT_MAX_AGE_DAYS=7
    |
    */

    'market_snapshot_max_age_days' => (int) env('MARKET_SNAPSHOT_MAX_AGE_DAYS', 7),

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
