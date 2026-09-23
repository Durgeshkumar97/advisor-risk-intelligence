<?php

declare(strict_types=1);

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Family weights
// ---------------------------------------------------------------------------

it('sums the pre-listing family weights to exactly 100', function () {
    expect(array_sum(config('ipo_risk.families.pre_listing')))->toBe(100);
});

it('sums the listed family weights to exactly 100', function () {
    expect(array_sum(config('ipo_risk.families.listed')))->toBe(100);
});

it('names the pre-listing families the ruleset expects', function () {
    expect(array_keys(config('ipo_risk.families.pre_listing')))->toEqualCanonicalizing([
        'earnings_quality', 'balance_sheet', 'governance',
        'issue_structure', 'valuation', 'institutional_demand',
    ]);
});

it('names the listed families the ruleset expects', function () {
    expect(array_keys(config('ipo_risk.families.listed')))->toEqualCanonicalizing([
        'liquidity', 'lockin', 'post_listing_delivery',
        'concentration', 'governance_drift', 'price_drift',
    ]);
});

// I7, at the config layer — the absence of a gmp weight IS the design.
it('gives grey-market premium no weight in either family set', function () {
    expect(config('ipo_risk.families.pre_listing'))->not->toHaveKey('gmp')
        ->and(config('ipo_risk.families.listed'))->not->toHaveKey('gmp');
});

// ---------------------------------------------------------------------------
// Gate floors
// ---------------------------------------------------------------------------

it('defines every hard gate floor in range', function () {
    $floors = config('ipo_risk.gate_floors');

    expect($floors)->toHaveKeys([
        'GOING_CONCERN', 'NEGATIVE_CFO', 'NEGATIVE_CFO_2YR',
        'INTEREST_COVER_SUB_1', 'SINGLE_YEAR_DISCLOSURE',
        'PROMOTER_LITIGATION_CRIMINAL', 'REGULATOR_ACTION',
        'RESTATEMENT_MATERIAL', 'ASM_GSM_FLAGGED',
    ]);

    foreach ($floors as $code => $floor) {
        expect($floor)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

// ---------------------------------------------------------------------------
// Coverage
// ---------------------------------------------------------------------------

it('keeps every coverage penalty negative', function () {
    foreach (config('ipo_risk.coverage.penalties') as $key => $penalty) {
        expect($penalty)->toBeLessThan(0);
    }
});

it('caps only penalties that can fire more than once', function () {
    foreach (array_keys(config('ipo_risk.coverage.penalty_caps')) as $key) {
        expect(config('ipo_risk.coverage.penalties'))->toHaveKey($key);
    }
});

it('orders the coverage band edges downward', function () {
    $bands = config('ipo_risk.coverage.bands');

    expect($bands['high'])->toBeGreaterThan($bands['moderate'])
        ->and($bands['moderate'])->toBeGreaterThan($bands['limited']);
});

it('suppresses at the insufficient-coverage edge', function () {
    expect(config('ipo_risk.coverage.suppression_threshold'))
        ->toBe(config('ipo_risk.coverage.bands.limited'));
});

// ---------------------------------------------------------------------------
// D2 — no parallel risk bands live here
// ---------------------------------------------------------------------------

it('defines no risk-band edges of its own', function () {
    expect(config('ipo_risk'))->not->toHaveKey('risk_bands')
        ->and(config('ipo_risk'))->not->toHaveKey('bands');
});

it('binds the exact config keys RiskScore::levelFromScore reads', function () {
    expect(config('ipo_risk.bound_config_keys'))
        ->toEqualCanonicalizing(['risk.low_threshold', 'risk.high_threshold']);
});

// ---------------------------------------------------------------------------
// Prohibited terms — both origins present
// ---------------------------------------------------------------------------

it('carries the house-rule terms alongside the module list', function () {
    expect(config('ipo_risk.prohibited_terms'))
        ->toContain('should', 'consider', 'recommend', 'review', 'discuss')
        ->toContain('buy', 'sell', 'good investment', 'we suggest');
});

it('lists no prohibited term twice', function () {
    $terms = config('ipo_risk.prohibited_terms');

    expect(array_unique($terms))->toHaveCount(count($terms));
});
