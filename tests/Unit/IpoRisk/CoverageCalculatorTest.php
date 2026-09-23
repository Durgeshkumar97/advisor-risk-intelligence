<?php

declare(strict_types=1);

use App\Services\IpoRisk\Scoring\CoverageCalculator;

uses(\Tests\TestCase::class);

function coverage(): CoverageCalculator
{
    return new CoverageCalculator;
}

function preListingWeights(): array
{
    return config('ipo_risk.families.pre_listing');
}

// ---------------------------------------------------------------------------
// Base coverage — share of applicable weight populated
// ---------------------------------------------------------------------------

it('reports full coverage when every family is populated', function () {
    $scores = array_map(static fn (): int => 50, preListingWeights());

    expect(coverage()->baseCoverage(preListingWeights(), $scores))->toBe(100);
});

it('reports zero coverage when nothing is populated', function () {
    $scores = array_map(static fn (): ?int => null, preListingWeights());

    expect(coverage()->baseCoverage(preListingWeights(), $scores))->toBe(0);
});

it('measures weight populated, not count of factors populated', function () {
    // earnings_quality 25 + balance_sheet 20 = 45 of 100
    $scores = [
        'earnings_quality' => 70, 'balance_sheet' => 30, 'governance' => null,
        'issue_structure' => null, 'valuation' => null, 'institutional_demand' => null,
    ];

    expect(coverage()->baseCoverage(preListingWeights(), $scores))->toBe(45);
});

it('scores the two lightest families far below half, though they are two of six', function () {
    // valuation 12 + institutional_demand 8 = 20 of 100
    $scores = [
        'earnings_quality' => null, 'balance_sheet' => null, 'governance' => null,
        'issue_structure' => null, 'valuation' => 40, 'institutional_demand' => 40,
    ];

    expect(coverage()->baseCoverage(preListingWeights(), $scores))->toBe(20);
});

it('reports zero coverage when no weight is applicable at all', function () {
    expect(coverage()->baseCoverage([], ['a' => 10]))->toBe(0);
});

// ---------------------------------------------------------------------------
// Penalties
// ---------------------------------------------------------------------------

it('docks each configured penalty', function (string $key, int $expected) {
    expect(coverage()->penaltyTotal([$key => 1]))->toBe($expected);
})->with([
    ['peer_set_failed', -15],
    ['under_two_years_audited', -20],
    ['ocr_source', -10],
    ['secondary_source', -5],
    ['stale_over_90_days', -10],
]);

it('accumulates several different penalties', function () {
    expect(coverage()->penaltyTotal(['peer_set_failed' => 1, 'ocr_source' => 1]))->toBe(-25);
});

it('charges a repeatable penalty once per occurrence', function () {
    expect(coverage()->penaltyTotal(['secondary_source' => 3]))->toBe(-15);
});

it('caps a repeatable penalty however many times it fires', function () {
    expect(coverage()->penaltyTotal(['secondary_source' => 4]))->toBe(-20)
        ->and(coverage()->penaltyTotal(['secondary_source' => 40]))->toBe(-20);
});

it('charges nothing for a penalty that did not occur', function () {
    expect(coverage()->penaltyTotal(['secondary_source' => 0, 'ocr_source' => 0]))->toBe(0)
        ->and(coverage()->penaltyTotal([]))->toBe(0);
});

it('refuses an unknown penalty key rather than ignoring a typo', function () {
    coverage()->penaltyTotal(['ocr_sauce' => 1]);
})->throws(InvalidArgumentException::class, 'Unknown coverage penalty');

it('refuses a negative occurrence count', function () {
    coverage()->penaltyTotal(['ocr_source' => -1]);
})->throws(InvalidArgumentException::class, 'negative number of times');

// ---------------------------------------------------------------------------
// Combined, and bounded
// ---------------------------------------------------------------------------

it('subtracts penalties from the populated base', function () {
    $scores = array_map(static fn (): int => 50, preListingWeights());

    expect(coverage()->calculate(preListingWeights(), $scores, ['ocr_source' => 1]))->toBe(90);
});

it('never falls below zero however many penalties fire', function () {
    $scores = [
        'earnings_quality' => 50, 'balance_sheet' => null, 'governance' => null,
        'issue_structure' => null, 'valuation' => null, 'institutional_demand' => null,
    ];

    expect(coverage()->calculate(preListingWeights(), $scores, [
        'peer_set_failed' => 1,
        'under_two_years_audited' => 1,
        'ocr_source' => 1,
        'secondary_source' => 10,
        'stale_over_90_days' => 1,
    ]))->toBe(0);
});

it('never rises above one hundred', function () {
    $scores = array_map(static fn (): int => 50, preListingWeights());

    expect(coverage()->calculate(preListingWeights(), $scores))->toBe(100);
});

// ---------------------------------------------------------------------------
// I8 — suppression threshold
// ---------------------------------------------------------------------------

it('suppresses below the threshold and not at it', function () {
    expect(coverage()->isSuppressed(39))->toBeTrue()
        ->and(coverage()->isSuppressed(40))->toBeFalse()
        ->and(coverage()->isSuppressed(0))->toBeTrue()
        ->and(coverage()->isSuppressed(100))->toBeFalse();
});

it('follows the configured suppression threshold', function () {
    config()->set('ipo_risk.coverage.suppression_threshold', 60);

    expect(coverage()->isSuppressed(55))->toBeTrue()
        ->and(coverage()->isSuppressed(60))->toBeFalse();
});

it('suppresses a well-populated filing once penalties drag it under', function () {
    // 45 base, minus peer_set_failed 15 = 30
    $scores = [
        'earnings_quality' => 70, 'balance_sheet' => 30, 'governance' => null,
        'issue_structure' => null, 'valuation' => null, 'institutional_demand' => null,
    ];

    $score = coverage()->calculate(preListingWeights(), $scores, ['peer_set_failed' => 1]);

    expect($score)->toBe(30)
        ->and(coverage()->isSuppressed($score))->toBeTrue();
});
