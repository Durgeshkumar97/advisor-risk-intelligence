<?php

use App\Models\PortfolioAsset;
use App\Services\RiskEngine\PortfolioRiskCalculator;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Helper — build a PortfolioAsset without touching the DB
// ---------------------------------------------------------------------------

function makeAsset(array $attrs): PortfolioAsset
{
    return (new PortfolioAsset)->forceFill(array_merge([
        'asset_type' => 'stock',
        'name' => 'Test Asset',
        'quantity' => 1,
        'buy_price' => 0,
        'current_price' => 0,
        'symbol' => null,
        'isin' => null,
        'risk_level' => 'MEDIUM',
    ], $attrs));
}

function calc(): PortfolioRiskCalculator
{
    return new PortfolioRiskCalculator;
}

// ---------------------------------------------------------------------------
// Empty / guard cases
// ---------------------------------------------------------------------------

it('returns zero result for an empty collection', function () {
    $result = calc()->calculate(collect());

    expect($result['score'])->toBe(0.0)
        ->and($result['volatility'])->toBe(0.0)
        ->and($result['drawdown'])->toBe(0.0)
        ->and($result['risk_flags'])->toContain('NO_HOLDINGS');
});

it('returns zero result when total current value is zero', function () {
    $assets = collect([
        makeAsset(['current_value' => 0, 'invested_value' => 100, 'risk_score' => 65]),
    ]);

    $result = calc()->calculate($assets);

    expect($result['score'])->toBe(0.0)
        ->and($result['risk_flags'])->toContain('NO_HOLDINGS');
});

// ---------------------------------------------------------------------------
// Return structure
// ---------------------------------------------------------------------------

it('returns all required keys', function () {
    $assets = collect([
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
    ]);

    $result = calc()->calculate($assets);

    expect($result)->toHaveKeys(['score', 'volatility', 'drawdown', 'next_action', 'risk_flags', 'meta']);
    expect($result['meta'])->toHaveKeys([
        'composition_score', 'concentration_score', 'equity_ratio_score',
        'drawdown_score', 'asset_count', 'risk_level', 'calculator_version',
        'stock_risk_fallback_count',
    ]);
});

// ---------------------------------------------------------------------------
// Composition score (factor 1)
// ---------------------------------------------------------------------------

it('composition_score is the allocation-weighted average of per-asset risk scores', function () {
    // Two equal-value assets with risk scores 80 and 40 → weighted avg = 60
    $assets = collect([
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 80]),
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 40]),
    ]);

    $result = calc()->calculate($assets);

    expect($result['meta']['composition_score'])->toBe(60.0);
});

// ---------------------------------------------------------------------------
// Concentration risk flags (factor 2 — HHI)
// ---------------------------------------------------------------------------

it('sets HIGH_CONCENTRATION flag when one asset dominates the portfolio', function () {
    // 3 assets: 900 / 50 / 50 → concentration score ≈ 72%
    $assets = collect([
        makeAsset(['current_value' => 900, 'invested_value' => 900, 'risk_score' => 65]),
        makeAsset(['current_value' => 50, 'invested_value' => 50, 'risk_score' => 65]),
        makeAsset(['current_value' => 50, 'invested_value' => 50, 'risk_score' => 65]),
    ]);

    expect(calc()->calculate($assets)['risk_flags'])->toContain('HIGH_CONCENTRATION');
});

it('sets MODERATE_CONCENTRATION flag for an uneven but not extreme split', function () {
    // 5 assets: 700 / 75 / 75 / 75 / 75 → concentration score ≈ 39%
    $assets = collect([
        makeAsset(['current_value' => 700, 'invested_value' => 700, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
    ]);

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->toContain('MODERATE_CONCENTRATION')
        ->and($flags)->not->toContain('HIGH_CONCENTRATION');
});

it('does not set concentration flags for a perfectly equal portfolio', function () {
    $assets = collect(array_fill(0, 6, null))->map(
        fn () => makeAsset(['current_value' => 100, 'invested_value' => 100, 'risk_score' => 50])
    );

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->not->toContain('HIGH_CONCENTRATION')
        ->and($flags)->not->toContain('MODERATE_CONCENTRATION');
});

// ---------------------------------------------------------------------------
// Equity ratio flags (factor 3)
// ---------------------------------------------------------------------------

it('sets EQUITY_HEAVY flag when equity assets exceed 90% of the portfolio', function () {
    // 10 equal stocks — 100% equity
    $assets = collect(array_fill(0, 10, null))->map(
        fn () => makeAsset(['asset_type' => 'stock', 'current_value' => 100, 'invested_value' => 100, 'risk_score' => 65])
    );

    expect(calc()->calculate($assets)['risk_flags'])->toContain('EQUITY_HEAVY');
});

it('sets UNDERWEIGHTED_EQUITY flag when equity is less than 10% of the portfolio', function () {
    // 950 in bonds + 50 in stock
    $assets = collect([
        makeAsset(['asset_type' => 'bond', 'current_value' => 950, 'invested_value' => 950, 'risk_score' => 15]),
        makeAsset(['asset_type' => 'stock', 'current_value' => 50, 'invested_value' => 50, 'risk_score' => 65]),
    ]);

    expect(calc()->calculate($assets)['risk_flags'])->toContain('UNDERWEIGHTED_EQUITY');
});

it('does not count low-scoring mutual funds as equity for the ratio', function () {
    // Liquid/overnight MF: risk_score < 25 → excluded from equity ratio
    $assets = collect([
        makeAsset(['asset_type' => 'mutual_fund', 'current_value' => 900, 'invested_value' => 900, 'risk_score' => 17]),
        makeAsset(['asset_type' => 'stock',       'current_value' => 100, 'invested_value' => 100, 'risk_score' => 65]),
    ]);

    // equity value = 100 (only the stock), ratio = 10% — at boundary, NOT EQUITY_HEAVY
    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->not->toContain('EQUITY_HEAVY');
});

// ---------------------------------------------------------------------------
// Drawdown flags (factor 4)
// ---------------------------------------------------------------------------

it('sets SIGNIFICANT_DRAWDOWN flag for portfolio losses above 15%', function () {
    // 5 equal assets, each down 16%: invested=200, current=168
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['current_value' => 168, 'invested_value' => 200, 'risk_score' => 65])
    );

    expect(calc()->calculate($assets)['risk_flags'])->toContain('SIGNIFICANT_DRAWDOWN');
});

it('sets MODERATE_DRAWDOWN flag for portfolio losses between 7% and 15%', function () {
    // invested=1000, current=920 → 8% loss
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['current_value' => 184, 'invested_value' => 200, 'risk_score' => 65])
    );

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->toContain('MODERATE_DRAWDOWN')
        ->and($flags)->not->toContain('SIGNIFICANT_DRAWDOWN');
});

it('does not set drawdown flags when portfolio is flat', function () {
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['current_value' => 200, 'invested_value' => 200, 'risk_score' => 65])
    );

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->not->toContain('SIGNIFICANT_DRAWDOWN')
        ->and($flags)->not->toContain('MODERATE_DRAWDOWN');
});

it('does not set drawdown flags when portfolio has unrealised gains', function () {
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['current_value' => 220, 'invested_value' => 200, 'risk_score' => 65])
    );

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->not->toContain('SIGNIFICANT_DRAWDOWN')
        ->and($flags)->not->toContain('MODERATE_DRAWDOWN');
});

// ---------------------------------------------------------------------------
// Diversification flags
// ---------------------------------------------------------------------------

it('sets LOW_DIVERSIFICATION flag for fewer than 4 assets', function () {
    $assets = collect(array_fill(0, 3, null))->map(
        fn () => makeAsset(['current_value' => 100, 'invested_value' => 100, 'risk_score' => 65])
    );

    expect(calc()->calculate($assets)['risk_flags'])->toContain('LOW_DIVERSIFICATION');
});

it('does not set LOW_DIVERSIFICATION flag for 4 or more assets', function () {
    $assets = collect(array_fill(0, 4, null))->map(
        fn () => makeAsset(['current_value' => 100, 'invested_value' => 100, 'risk_score' => 65])
    );

    expect(calc()->calculate($assets)['risk_flags'])->not->toContain('LOW_DIVERSIFICATION');
});

it('sets OVER_DIVERSIFICATION flag for more than 25 assets', function () {
    $assets = collect(array_fill(0, 26, null))->map(
        fn () => makeAsset(['current_value' => 100, 'invested_value' => 100, 'risk_score' => 45, 'asset_type' => 'mutual_fund'])
    );

    expect(calc()->calculate($assets)['risk_flags'])->toContain('OVER_DIVERSIFICATION');
});

// ---------------------------------------------------------------------------
// Market multiplier
// ---------------------------------------------------------------------------

it('applies the market multiplier from config to the final score', function () {
    // 5 equal stocks with no drawdown — deterministic base score
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['asset_type' => 'stock', 'current_value' => 200, 'invested_value' => 200, 'risk_score' => 65])
    );

    config(['risk.market_multiplier' => 1.0]);
    $score100 = calc()->calculate($assets)['score'];

    config(['risk.market_multiplier' => 1.2]);
    $score120 = calc()->calculate($assets)['score'];

    expect($score120)->toBeGreaterThan($score100);
});

it('takes an explicit market multiplier per call, overriding config', function () {
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['asset_type' => 'stock', 'current_value' => 200, 'invested_value' => 200, 'risk_score' => 65])
    );

    config(['risk.market_multiplier' => 1.0]);

    $withConfig = calc()->calculate($assets)['score'];
    $withOverride = calc()->calculate($assets, 1.2)['score'];

    expect($withOverride)->toBeGreaterThan($withConfig)
        ->and($withOverride)->toBe(round($withConfig * 1.2, 2));
});

it('does not leak a per-call multiplier into a later call — the config() mutation regression', function () {
    // ProcessPortfolioFile used to assign the snapshot multiplier into
    // config(), which is process-global. Inside one queue:work process a
    // later job with no snapshot then inherited the previous job's value,
    // making the score depend on job ordering.
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['asset_type' => 'stock', 'current_value' => 200, 'invested_value' => 200, 'risk_score' => 65])
    );

    config(['risk.market_multiplier' => 1.0]);

    $baseline = calc()->calculate($assets)['score'];

    // A call with a high explicit multiplier — as a job WITH a snapshot makes.
    calc()->calculate($assets, 1.3);

    // The next call without one — a job with NO snapshot — must be unaffected.
    $after = calc()->calculate($assets)['score'];

    expect($after)->toBe($baseline)
        ->and(config('risk.market_multiplier'))->toBe(1.0);
});

it('clamps the market multiplier to the allowed range', function () {
    $assets = collect(array_fill(0, 5, null))->map(
        fn () => makeAsset(['asset_type' => 'stock', 'current_value' => 200, 'invested_value' => 200, 'risk_score' => 65])
    );

    config(['risk.market_multiplier' => 99.0]); // way above max (1.30)

    $result = calc()->calculate($assets);

    // Score must still be clamped 0–100
    expect($result['score'])->toBeLessThanOrEqual(100.0)
        ->and($result['score'])->toBeGreaterThanOrEqual(0.0);
});

// ---------------------------------------------------------------------------
// level() thresholds
// ---------------------------------------------------------------------------

it('level() returns LOW, MEDIUM, HIGH according to configured thresholds', function () {
    $calculator = calc();

    config(['risk.low_threshold' => 30, 'risk.high_threshold' => 70]);

    expect($calculator->level(0.0))->toBe('LOW');
    expect($calculator->level(29.9))->toBe('LOW');
    expect($calculator->level(30.0))->toBe('MEDIUM');
    expect($calculator->level(69.9))->toBe('MEDIUM');
    expect($calculator->level(70.0))->toBe('HIGH');
    expect($calculator->level(100.0))->toBe('HIGH');
});

// ---------------------------------------------------------------------------
// next_action priority
// ---------------------------------------------------------------------------

it('next_action prioritises significant drawdown above all other signals', function () {
    // 5 stocks, concentrated (900/50/50 style), with 16% drawdown
    $assets = collect([
        makeAsset(['asset_type' => 'stock', 'current_value' => 840, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'stock', 'current_value' => 840, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'stock', 'current_value' => 840, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'stock', 'current_value' => 840, 'invested_value' => 1000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'stock', 'current_value' => 840, 'invested_value' => 1000, 'risk_score' => 65]),
    ]);

    expect(calc()->calculate($assets)['next_action'])->toContain('unrealised losses');
});

it('next_action suggests staying the course for a very low-risk portfolio', function () {
    // All bonds, no drawdown, equal weights
    $assets = collect(array_fill(0, 10, null))->map(
        fn () => makeAsset(['asset_type' => 'bond', 'current_value' => 100, 'invested_value' => 100, 'risk_score' => 15])
    );

    config(['risk.low_threshold' => 30]);

    expect(calc()->calculate($assets)['next_action'])->toContain('low');
});

it('next_action returns the default message when no special condition is triggered', function () {
    // Mixed moderate portfolio: 4 MFs + 2 bonds, equal weights, no drawdown
    // → score between 30–75, equity ratio ~67%, no flags
    $assets = collect([
        makeAsset(['asset_type' => 'mutual_fund', 'current_value' => 210, 'invested_value' => 210, 'risk_score' => 45]),
        makeAsset(['asset_type' => 'mutual_fund', 'current_value' => 210, 'invested_value' => 210, 'risk_score' => 45]),
        makeAsset(['asset_type' => 'mutual_fund', 'current_value' => 210, 'invested_value' => 210, 'risk_score' => 45]),
        makeAsset(['asset_type' => 'mutual_fund', 'current_value' => 210, 'invested_value' => 210, 'risk_score' => 45]),
        makeAsset(['asset_type' => 'bond',        'current_value' => 80, 'invested_value' => 80, 'risk_score' => 15]),
        makeAsset(['asset_type' => 'bond',        'current_value' => 80, 'invested_value' => 80, 'risk_score' => 15]),
    ]);

    config(['risk.low_threshold' => 30, 'risk.high_threshold' => 70]);

    expect(calc()->calculate($assets)['next_action'])->toContain('acceptable risk parameters');
});

// ---------------------------------------------------------------------------
// Volatility
// ---------------------------------------------------------------------------

it('volatility is the standard deviation of per-asset risk scores', function () {
    // [30, 70] → std dev = sqrt(800) ≈ 28.28
    $assets = collect([
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 30]),
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 70]),
    ]);

    expect(calc()->calculate($assets)['volatility'])->toBe(28.28);
});

it('volatility is zero for a single-asset portfolio', function () {
    $assets = collect([
        makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
    ]);

    expect(calc()->calculate($assets)['volatility'])->toBe(0.0);
});

// ---------------------------------------------------------------------------
// meta.stock_risk_fallback_count — informational aggregation, does not
// affect composition/concentration/equity-ratio/drawdown or the composite score
// ---------------------------------------------------------------------------

it('counts equity holdings that fell back to a static stock risk score', function () {
    $assets = collect([
        makeAsset([
            'asset_type' => 'stock', 'current_value' => 1000, 'invested_value' => 1000,
            'risk_score' => 65, 'meta' => ['stock_risk' => ['source' => 'live']],
        ]),
        makeAsset([
            'asset_type' => 'stock', 'current_value' => 1000, 'invested_value' => 1000,
            'risk_score' => 65, 'meta' => ['stock_risk' => ['source' => 'fallback_unavailable']],
        ]),
        makeAsset([
            'asset_type' => 'stock', 'current_value' => 1000, 'invested_value' => 1000,
            'risk_score' => 80, 'meta' => ['stock_risk' => ['source' => 'fallback_unavailable']],
        ]),
        makeAsset([
            // no meta at all — matches how ProcessPortfolioFile writes non-stock rows
            'asset_type' => 'bond', 'current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 15,
        ]),
    ]);

    $result = calc()->calculate($assets);

    expect($result['meta']['stock_risk_fallback_count'])->toBe(2)
        // Purely informational — the score/factors are unaffected by fallback usage.
        ->and($result['meta']['composition_score'])->toBe(round((65 + 65 + 80 + 15) / 4, 2));
});

it('reports a zero fallback count when every stock holding used live data', function () {
    $assets = collect([
        makeAsset([
            'asset_type' => 'stock', 'current_value' => 1000, 'invested_value' => 1000,
            'risk_score' => 50, 'meta' => ['stock_risk' => ['source' => 'live']],
        ]),
        makeAsset([
            'asset_type' => 'stock', 'current_value' => 1000, 'invested_value' => 1000,
            'risk_score' => 80, 'meta' => ['stock_risk' => ['source' => 'live']],
        ]),
    ]);

    expect(calc()->calculate($assets)['meta']['stock_risk_fallback_count'])->toBe(0);
});

it('reports a zero fallback count and does not error for a fixed-income-only portfolio', function () {
    $assets = collect([
        makeAsset(['asset_type' => 'bond', 'current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 15]),
        makeAsset(['asset_type' => 'cash', 'current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 5]),
    ]);

    // Neither asset has a meta.stock_risk key at all — matches production
    // output for non-stock rows (ProcessPortfolioFile never writes that key
    // for them). Confirms the aggregation doesn't error on a missing key.
    expect($assets->first()->meta)->toBeNull();

    expect(calc()->calculate($assets)['meta']['stock_risk_fallback_count'])->toBe(0);
});
