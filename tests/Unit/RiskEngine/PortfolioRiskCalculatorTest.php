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

it('sets HIGH_CONCENTRATION when one 70% holding sits among four small ones', function () {
    // 5 assets: 700 / 75 / 75 / 75 / 75 (shares .7 / .075 ×4).
    // HHI = .49 + 4 × .005625 = .5125; top share = .7;
    // concentration = 100 × (0.5 × .5125 + 0.5 × .7) = 60.625 → above 60.
    // (The old normalised HHI gave ≈ 39 here, i.e. MODERATE.)
    $assets = collect([
        makeAsset(['current_value' => 700, 'invested_value' => 700, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
        makeAsset(['current_value' => 75, 'invested_value' => 75, 'risk_score' => 65]),
    ]);

    $flags = calc()->calculate($assets)['risk_flags'];

    expect($flags)->toContain('HIGH_CONCENTRATION')
        ->and($flags)->not->toContain('MODERATE_CONCENTRATION');
});

it('sets MODERATE_CONCENTRATION for an uneven but not extreme split', function () {
    // 4 assets: 400 / 200 / 200 / 200 → HHI = .16 + 3 × .04 = .28; top = .4;
    // concentration = 100 × (0.5 × .28 + 0.5 × .4) = 34 → between 30 and 60.
    $assets = collect([
        makeAsset(['current_value' => 400, 'invested_value' => 400, 'risk_score' => 65]),
        makeAsset(['current_value' => 200, 'invested_value' => 200, 'risk_score' => 65]),
        makeAsset(['current_value' => 200, 'invested_value' => 200, 'risk_score' => 65]),
        makeAsset(['current_value' => 200, 'invested_value' => 200, 'risk_score' => 65]),
    ]);

    $result = calc()->calculate($assets);

    expect($result['meta']['concentration_score'])->toBe(34.0)
        ->and($result['risk_flags'])->toContain('MODERATE_CONCENTRATION')
        ->and($result['risk_flags'])->not->toContain('HIGH_CONCENTRATION');
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

it('next_action states the low band, and nothing about the allocation, for a very low-risk portfolio', function () {
    // All bonds, no drawdown, equal weights
    $assets = collect(array_fill(0, 10, null))->map(
        fn () => makeAsset(['asset_type' => 'bond', 'current_value' => 100, 'invested_value' => 100, 'risk_score' => 15])
    );

    config(['risk.low_threshold' => 30]);

    expect(calc()->calculate($assets)['next_action'])->toBe('Overall risk score is in the low band (below 30).');
});

it('next_action states the medium band when no special condition is triggered', function () {
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

    expect(calc()->calculate($assets)['next_action'])->toBe('Overall risk score is in the medium band (30 to below 70).');
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

// ---------------------------------------------------------------------------
// Unknown cost basis (invested_value = null)
// ---------------------------------------------------------------------------

it('measures unrealised loss only over holdings whose cost is known', function () {
    // A: cost 80,000 → now 50,000 (−37.5%). B: cost unknown, now 50,000.
    // Before this change B's null cost summed as 0, so B's 50,000 offset A's
    // loss (+20,000 overall), drawdown read 0 and the score was 44.50.
    // Measured over A alone: 37.5% loss → drawdown factor 100 → score 64.50.
    // Two equal holdings now carry concentration 50 (HHI = top share = .5):
    // +50 × 0.25 = +12.5, so the scores above become 57.00 and 77.00.
    $assets = collect([
        makeAsset(['current_value' => 50000, 'invested_value' => 80000, 'risk_score' => 65]),
        makeAsset(['current_value' => 50000, 'invested_value' => null, 'risk_score' => 65]),
    ]);

    $result = calc()->calculate($assets, 1.0);

    expect($result['drawdown'])->toBe(37.5)
        ->and($result['meta']['drawdown_score'])->toBe(100.0)
        ->and($result['score'])->toBe(77.0)
        ->and($result['risk_flags'])->toContain('SIGNIFICANT_DRAWDOWN');
});

// ---------------------------------------------------------------------------
// Holdings valued at cost (meta.value_basis = 'cost')
// ---------------------------------------------------------------------------

it('leaves a holding valued at cost out of the unrealised-loss measure, while still weighting it', function () {
    // A: cost 1,00,000 → now 85,000 (−15%).
    // B: a US stock whose export has no market price, carried at its cost of
    //    1,00,000. Its "current value" equals its cost by construction.
    // Counting B would read as "1,00,000 that has not moved": the loss
    // becomes 15,000 on 2,00,000 = 7.5%, drawdown factor 25, score 49.66.
    // Measured over A alone: 15% → drawdown factor 50 → score 54.66.
    // Concentration is now 100 × (0.5 × .5033 + 0.5 × .5405) = 52.19, which adds
    // (52.19 − .66) × 0.25 = +12.88 to each: 67.55 and 62.55.
    $assets = collect([
        makeAsset(['current_value' => 85000, 'invested_value' => 100000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'foreign_stock', 'current_value' => 100000, 'invested_value' => 100000, 'risk_score' => 65, 'meta' => ['value_basis' => 'cost']]),
    ]);

    $result = calc()->calculate($assets, 1.0);

    expect($result['drawdown'])->toBe(15.0)
        ->and($result['meta']['drawdown_score'])->toBe(50.0)
        ->and($result['meta']['total_invested'])->toBe(100000.0)      // the measured subset: A only
        ->and($result['score'])->toBe(67.55)
        // B still counts for allocation: the portfolio is 1,85,000, all equity.
        ->and($result['meta']['total_current'])->toBe(185000.0)
        ->and($result['meta']['equity_ratio_pct'])->toBe(100.0)
        ->and($result['meta']['hhi'])->toBe(0.5033);
});

it('measures a market-valued holding exactly as before, whatever else its meta says', function () {
    $assets = collect([
        makeAsset(['current_value' => 85000, 'invested_value' => 100000, 'risk_score' => 65, 'meta' => ['value_basis' => 'market', 'currency' => 'USD']]),
        makeAsset(['current_value' => 100000, 'invested_value' => 100000, 'risk_score' => 65]),
    ]);

    expect(calc()->calculate($assets, 1.0)['drawdown'])->toBe(7.5);
});

// ---------------------------------------------------------------------------
// Risk-flag thresholds — one test either side of each named constant, so a
// change to any of them is a failing test, not a silent change of behaviour.
// ---------------------------------------------------------------------------

/** Two stocks split $share / (1 − $share); concentration = 100 × (0.5 × (s² + (1−s)²) + 0.5 × s). */
function twoStockSplit(float $share): array
{
    return calc()->calculate(collect([
        makeAsset(['current_value' => $share * 100000, 'invested_value' => $share * 100000, 'risk_score' => 65]),
        makeAsset(['current_value' => (1 - $share) * 100000, 'invested_value' => (1 - $share) * 100000, 'risk_score' => 65]),
    ]), 1.0);
}

/** A stock holding $share of the value plus nine equal holdings sharing the rest. */
function topPlusNine(float $share): array
{
    $rest = (1 - $share) / 9 * 100000;
    $holdings = [makeAsset(['current_value' => $share * 100000, 'invested_value' => $share * 100000, 'risk_score' => 65])];
    for ($i = 0; $i < 9; $i++) {
        $holdings[] = makeAsset(['current_value' => $rest, 'invested_value' => $rest, 'risk_score' => 65]);
    }

    return calc()->calculate(collect($holdings), 1.0);
}

/** One stock and one bond; the stock is $equityShare of the value. */
function equitySplit(float $equityShare): array
{
    return calc()->calculate(collect([
        makeAsset(['current_value' => $equityShare * 100000, 'invested_value' => $equityShare * 100000, 'risk_score' => 65]),
        makeAsset(['asset_type' => 'bond', 'current_value' => (1 - $equityShare) * 100000, 'invested_value' => (1 - $equityShare) * 100000, 'risk_score' => 15]),
    ]), 1.0);
}

/** Four equal bonds that cost 1,00,000 in total and are now worth $currentTotal. */
function bondsWorth(float $currentTotal): array
{
    return calc()->calculate(collect(array_map(
        fn () => makeAsset(['asset_type' => 'bond', 'current_value' => $currentTotal / 4, 'invested_value' => 25000, 'risk_score' => 15]),
        range(1, 4),
    )), 1.0);
}

it('raises HIGH_CONCENTRATION above a concentration score of 60 and not below', function () {
    // s = .66: HHI = .4356 + .1156 = .5512; 100 × (.2756 + .33) = 60.56
    // s = .65: HHI = .4225 + .1225 = .5450; 100 × (.2725 + .325) = 59.75
    $above = twoStockSplit(0.66);
    $below = twoStockSplit(0.65);

    expect($above['meta']['concentration_score'])->toBe(60.56)
        ->and($above['risk_flags'])->toContain('HIGH_CONCENTRATION')
        ->and($below['meta']['concentration_score'])->toBe(59.75)
        ->and($below['risk_flags'])->toContain('MODERATE_CONCENTRATION')->not->toContain('HIGH_CONCENTRATION')
        ->and(PortfolioRiskCalculator::HIGH_CONCENTRATION_ABOVE)->toBe(60);
});

it('raises MODERATE_CONCENTRATION above a concentration score of 30 and not below', function () {
    // Top holding s, nine others of (1−s)/9 each: HHI = s² + (1−s)²/9.
    // s = .41: HHI = .206778; 100 × (.103389 + .205) = 30.84
    // s = .39: HHI = .193444; 100 × (.096722 + .195) = 29.17
    $above = topPlusNine(0.41);
    $below = topPlusNine(0.39);

    expect($above['risk_flags'])->toContain('MODERATE_CONCENTRATION')
        ->and($below['risk_flags'])->not->toContain('MODERATE_CONCENTRATION')
        ->and(PortfolioRiskCalculator::MODERATE_CONCENTRATION_ABOVE)->toBe(30);
});

it('raises EQUITY_HEAVY above 90% equity and not at 89%', function () {
    expect(equitySplit(0.91)['risk_flags'])->toContain('EQUITY_HEAVY')
        ->and(equitySplit(0.89)['risk_flags'])->not->toContain('EQUITY_HEAVY')
        ->and(PortfolioRiskCalculator::EQUITY_HEAVY_ABOVE)->toBe(0.90);
});

it('raises UNDERWEIGHTED_EQUITY below 10% equity and not at 11%', function () {
    expect(equitySplit(0.09)['risk_flags'])->toContain('UNDERWEIGHTED_EQUITY')
        ->and(equitySplit(0.11)['risk_flags'])->not->toContain('UNDERWEIGHTED_EQUITY')
        ->and(PortfolioRiskCalculator::UNDERWEIGHTED_EQUITY_BELOW)->toBe(0.10);
});

it('raises SIGNIFICANT_DRAWDOWN above a 15% loss and MODERATE_DRAWDOWN just below it', function () {
    $above = bondsWorth(84900);     // 15.1% below cost
    $below = bondsWorth(85100);     // 14.9% below cost

    expect($above['risk_flags'])->toContain('SIGNIFICANT_DRAWDOWN')
        ->and($below['risk_flags'])->toContain('MODERATE_DRAWDOWN')->not->toContain('SIGNIFICANT_DRAWDOWN')
        ->and(PortfolioRiskCalculator::SIGNIFICANT_DRAWDOWN_ABOVE)->toBe(15);
});

it('raises MODERATE_DRAWDOWN above a 7% loss and not below', function () {
    expect(bondsWorth(92900)['risk_flags'])->toContain('MODERATE_DRAWDOWN')          // 7.1%
        ->and(bondsWorth(93100)['risk_flags'])->not->toContain('MODERATE_DRAWDOWN')  // 6.9%
        ->and(PortfolioRiskCalculator::MODERATE_DRAWDOWN_ABOVE)->toBe(7);
});

it('raises LOW_DIVERSIFICATION for 3 holdings and not for 4, OVER_DIVERSIFICATION for 26 and not for 25', function () {
    $portfolioOf = fn (int $n) => calc()->calculate(collect(array_map(
        fn () => makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
        range(1, $n),
    )), 1.0)['risk_flags'];

    expect($portfolioOf(3))->toContain('LOW_DIVERSIFICATION')
        ->and($portfolioOf(4))->not->toContain('LOW_DIVERSIFICATION')
        ->and($portfolioOf(25))->not->toContain('OVER_DIVERSIFICATION')
        ->and($portfolioOf(26))->toContain('OVER_DIVERSIFICATION')
        ->and(PortfolioRiskCalculator::LOW_DIVERSIFICATION_BELOW)->toBe(4)
        ->and(PortfolioRiskCalculator::OVER_DIVERSIFICATION_ABOVE)->toBe(25);
});

it('raises ELEVATED_OVERALL_RISK above a score of 75 and not at 75', function () {
    // One bond scored 100, 30% below cost, all in one holding:
    // (100×0.30 + 100×0.25 concentration + 0×0.25 equity + 100×0.20) = 75 before the multiplier.
    $asset = fn () => collect([makeAsset(['asset_type' => 'bond', 'current_value' => 70000, 'invested_value' => 100000, 'risk_score' => 100])]);

    $at = calc()->calculate($asset(), 1.0);
    $above = calc()->calculate($asset(), 1.02);

    expect($at['score'])->toBe(75.0)
        ->and($at['risk_flags'])->not->toContain('ELEVATED_OVERALL_RISK')
        ->and($above['score'])->toBe(76.5)
        ->and($above['risk_flags'])->toContain('ELEVATED_OVERALL_RISK')
        ->and(PortfolioRiskCalculator::ELEVATED_OVERALL_RISK_ABOVE)->toBe(75);
});

// ---------------------------------------------------------------------------
// The calculator's own fallback multiplier (used only if the config key is
// missing) is neutral, the same as config/risk.php's default.
// ---------------------------------------------------------------------------

it('falls back to a neutral multiplier of 1.0 when the config key is missing', function () {
    $risk = config('risk');
    unset($risk['market_multiplier']);
    config(['risk' => $risk]);

    // One bond scored 100, 30% below cost (concentration 100): 75 before any multiplier.
    $result = calc()->calculate(collect([
        makeAsset(['asset_type' => 'bond', 'current_value' => 70000, 'invested_value' => 100000, 'risk_score' => 100]),
    ]));

    expect($result['meta']['market_multiplier'])->toBe(1.0)
        ->and($result['score'])->toBe(75.0);
});

// ---------------------------------------------------------------------------
// Observation sentences state the band; they do not judge it.
// ---------------------------------------------------------------------------

/** buildNextAction() for a given score, flags and largest-holding share, without building a portfolio to reach it. */
function observationFor(float $score, array $flags = [], float $topShare = 0.5): string
{
    $method = new ReflectionMethod(PortfolioRiskCalculator::class, 'buildNextAction');
    $method->setAccessible(true);

    return $method->invoke(calc(), $score, $flags, $topShare);
}

it('states the band from the same thresholds the risk level uses', function () {
    config(['risk.low_threshold' => 25, 'risk.high_threshold' => 60]);

    expect(observationFor(24.99))->toBe('Overall risk score is in the low band (below 25).')
        ->and(observationFor(25.0))->toBe('Overall risk score is in the medium band (25 to below 60).')
        ->and(observationFor(59.99))->toBe('Overall risk score is in the medium band (25 to below 60).')
        ->and(observationFor(60.0))->toBe('Overall risk score is in the high band (60 or above).');

    // The sentence names the band the level tile shows, at every score.
    foreach ([0.0, 24.99, 25.0, 42.0, 59.99, 60.0, 72.0, 75.0] as $score) {
        expect(strtolower(calc()->level($score)))->toBe(explode(' ', explode('in the ', observationFor($score))[1])[0]);
    }
});

it('gives a score of 72 the high-band sentence, never "acceptable"', function () {
    // 70–75 is HIGH by level but was below the old "elevated" cut-off of 75,
    // so it fell through to "within acceptable risk parameters".
    expect(observationFor(72.0))->toBe('Overall risk score is in the high band (70 or above).')
        ->and(calc()->level(72.0))->toBe('HIGH');

    // A real portfolio that lands in the gap: nine crypto holdings and a bond,
    // equal weights, 6.9% below cost, under an EXTREME market snapshot (×1.30).
    $assets = collect(array_fill(0, 9, null))
        ->map(fn () => makeAsset(['asset_type' => 'crypto', 'current_value' => 931, 'invested_value' => 1000, 'risk_score' => 100]))
        ->push(makeAsset(['asset_type' => 'bond', 'current_value' => 931, 'invested_value' => 1000, 'risk_score' => 15]));

    $result = calc()->calculate($assets, 1.30);

    expect($result['score'])->toBeGreaterThanOrEqual(70.0)->toBeLessThanOrEqual(75.0)
        ->and($result['risk_flags'])->toBe([])
        ->and($result['meta']['risk_level'])->toBe('HIGH')
        ->and($result['next_action'])->toBe('Overall risk score is in the high band (70 or above).')
        ->and($result['next_action'])->not->toContain('acceptable');
});

it('says a score above 75 is above 75, reading the number from the flag threshold', function () {
    expect(observationFor(75.01))->toBe('Overall risk score is above '.PortfolioRiskCalculator::ELEVATED_OVERALL_RISK_ABOVE.' of 100.')
        ->and(observationFor(75.01))->toBe('Overall risk score is above 75 of 100.')
        ->and(observationFor(75.0))->toBe('Overall risk score is in the high band (70 or above).');
});

it('does not call a concentrated low-score portfolio balanced', function () {
    // Two bonds, 95% / 5%: HIGH_CONCENTRATION and LOW_DIVERSIFICATION, score under 30.
    $result = calc()->calculate(collect([
        makeAsset(['asset_type' => 'bond', 'current_value' => 95000, 'invested_value' => 95000, 'risk_score' => 15]),
        makeAsset(['asset_type' => 'bond', 'current_value' => 5000, 'invested_value' => 5000, 'risk_score' => 15]),
    ]));

    expect($result['risk_flags'])->toContain('HIGH_CONCENTRATION')->toContain('LOW_DIVERSIFICATION')
        ->and($result['next_action'])->toBe('Overall risk score is in the low band (below 30).');
});

it('has no observation sentence that judges the portfolio or tells the reader what to do', function () {
    $method = new ReflectionMethod(PortfolioRiskCalculator::class, 'buildNextAction');
    $lines = array_slice(file($method->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

    // Every sentence the method can return: a quoted string that ends in a full stop.
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*\\.)'/", implode('', $lines), $matches);
    $sentences = $matches[1];

    expect(count($sentences))->toBe(9);

    foreach ($sentences as $sentence) {
        foreach (['balanced', 'acceptable', 'healthy', 'appropriate', 'should', 'consider', 'recommend', 'review', 'discuss', 'reduce', 'increase', 'rebalance',
            // a level named as right or wrong, or a holding count read as underlying risk
            'underweight', 'too ', 'insufficient', 'very ', 'limited', 'concentrated', 'elevated'] as $word) {
            expect(stripos($sentence, $word))->toBeFalse("\"{$word}\" in: {$sentence}");
        }
    }
});

// ---------------------------------------------------------------------------
// Concentration = 100 × (0.5 × HHI + 0.5 × largest share)
// ---------------------------------------------------------------------------

/** $n holdings of equal value. */
function equalHoldings(int $n): array
{
    return calc()->calculate(collect(array_map(
        fn () => makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]),
        range(1, $n),
    )), 1.0);
}

it('states concentration observations as the largest holding\'s share', function () {
    expect(observationFor(70, ['HIGH_CONCENTRATION'], 1.0))
        ->toBe('The largest holding is 100% of portfolio value, and the overall risk score is above 65 of 100.')
        ->and(observationFor(40, ['MODERATE_CONCENTRATION'], 0.425))
        ->toBe('The largest holding is 42.5% of portfolio value.')
        ->and(observationFor(50, ['EQUITY_HEAVY']))
        ->toBe('Equity makes up more than 90% of the portfolio.');
});

it('scores a single holding as fully concentrated', function () {
    $result = equalHoldings(1);

    expect($result['meta']['concentration_score'])->toBe(100.0)
        ->and($result['risk_flags'])->toContain('HIGH_CONCENTRATION')
        ->and($result['meta']['top_holding_pct'])->toBe(100.0);
});

it('scores n equal holdings as 100 / n', function () {
    expect(equalHoldings(2)['meta']['concentration_score'])->toBe(50.0)
        ->and(equalHoldings(2)['risk_flags'])->toContain('MODERATE_CONCENTRATION')
        ->and(equalHoldings(4)['meta']['concentration_score'])->toBe(25.0)
        ->and(equalHoldings(4)['risk_flags'])->not->toContain('MODERATE_CONCENTRATION')
        ->and(equalHoldings(4)['risk_flags'])->not->toContain('HIGH_CONCENTRATION')
        ->and(equalHoldings(10)['meta']['concentration_score'])->toBe(10.0)
        ->and(equalHoldings(10)['meta']['top_holding_pct'])->toBe(10.0);
});

it('flags one dominant holding that a long tail would hide from HHI alone', function () {
    // 50,000 in one holding + fifty of 1,000: HHI = .25 + 50 × .0001 = .255
    // (25.5 alone, below 30); blend = 100 × (.1275 + .25) = 37.75.
    $assets = collect([makeAsset(['current_value' => 50000, 'invested_value' => 50000, 'risk_score' => 65])]);
    for ($i = 0; $i < 50; $i++) {
        $assets->push(makeAsset(['current_value' => 1000, 'invested_value' => 1000, 'risk_score' => 65]));
    }

    $result = calc()->calculate($assets, 1.0);

    expect($result['meta']['concentration_score'])->toBe(37.75)
        ->and($result['meta']['hhi'])->toBe(0.255)
        ->and($result['meta']['top_holding_pct'])->toBe(50.0)
        ->and($result['risk_flags'])->toContain('MODERATE_CONCENTRATION');
});
