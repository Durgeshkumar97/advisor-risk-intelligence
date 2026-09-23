<?php

declare(strict_types=1);

use App\Services\IpoRisk\Scoring\WeightedAggregator;

uses(\Tests\TestCase::class);

function aggregator(): WeightedAggregator
{
    return new WeightedAggregator;
}

// ---------------------------------------------------------------------------
// I2 — an absent input is removed and the rest renormalised. Never defaulted.
// ---------------------------------------------------------------------------

it('renormalises over the populated weights only', function () {
    $score = aggregator()->aggregate(
        ['A' => 30, 'B' => 70],
        ['A' => 100, 'B' => null],
    );

    expect($score)->toBe(100)
        ->and($score)->not->toBe(65)   // would mean B was blended in at 50
        ->and($score)->not->toBe(30)   // would mean B was treated as 0
        ->and($score)->not->toBe(50);
});

it('renormalises the other way round too', function () {
    expect(aggregator()->aggregate(['A' => 30, 'B' => 70], ['A' => null, 'B' => 20]))->toBe(20);
});

it('takes a true weighted mean when everything is populated', function () {
    // (30*100 + 70*20) / 100 = 44
    expect(aggregator()->aggregate(['A' => 30, 'B' => 70], ['A' => 100, 'B' => 20]))->toBe(44);
});

it('renormalises across three of six families', function () {
    $weights = [
        'earnings_quality' => 25, 'balance_sheet' => 20, 'governance' => 20,
        'issue_structure' => 15, 'valuation' => 12, 'institutional_demand' => 8,
    ];

    // Populated: 25*80 + 20*60 + 12*40 = 2000 + 1200 + 480 = 3680 over 57 => 64.56 => 65
    $scores = [
        'earnings_quality' => 80, 'balance_sheet' => 60, 'governance' => null,
        'issue_structure' => null, 'valuation' => 40, 'institutional_demand' => null,
    ];

    expect(aggregator()->aggregate($weights, $scores))->toBe(65);
});

// ---------------------------------------------------------------------------
// I3 (aggregator half) — nothing populated means no number, not a zero
// ---------------------------------------------------------------------------

it('returns null when every input is absent', function () {
    expect(aggregator()->aggregate(['A' => 30, 'B' => 70], ['A' => null, 'B' => null]))->toBeNull();
});

it('returns null when there are no weights at all', function () {
    expect(aggregator()->aggregate([], ['A' => 50]))->toBeNull();
});

it('returns null rather than zero, so an absent score is never a good score', function () {
    expect(aggregator()->aggregate(['A' => 30], []))->toBeNull();
});

it('ignores a score whose key carries no weight', function () {
    expect(aggregator()->aggregate(['A' => 30], ['A' => 60, 'ZZ' => 100]))->toBe(60);
});

it('ignores a zero or negative weight even when its score is populated', function () {
    expect(aggregator()->aggregate(['A' => 30, 'B' => 0, 'C' => -5], ['A' => 60, 'B' => 100, 'C' => 100]))
        ->toBe(60);
});

// ---------------------------------------------------------------------------
// I11 — integer 0-100 on the way out
// ---------------------------------------------------------------------------

it('clamps out-of-range inputs into 0-100', function (?int $score, int $expected) {
    expect(aggregator()->aggregate(['A' => 100], ['A' => $score]))->toBe($expected);
})->with([
    'far above' => [1000, 100],
    'just above' => [101, 100],
    'far below' => [-1000, 0],
    'just below' => [-1, 0],
    'top of range' => [100, 100],
    'bottom of range' => [0, 0],
]);

it('clamps a mix of out-of-range inputs before weighting, not after', function () {
    // Clamped first: (50*100 + 50*0)/100 = 50. Unclamped it would be (50*200 + 50*-100)/100 = 50 too,
    // so use an asymmetric pair where the difference shows: (50*100 + 50*0)/100 = 50 vs (50*400 + 50*0)/100 = 200.
    expect(aggregator()->aggregate(['A' => 50, 'B' => 50], ['A' => 400, 'B' => 0]))->toBe(50);
});

it('always returns an integer', function () {
    // (1*33 + 1*34 + 1*35)/3 = 34
    expect(aggregator()->aggregate(['A' => 1, 'B' => 1, 'C' => 1], ['A' => 33, 'B' => 34, 'C' => 35]))
        ->toBeInt()->toBe(34);
});

it('rounds a half up', function () {
    // (1*50 + 1*51)/2 = 50.5
    expect(aggregator()->aggregate(['A' => 1, 'B' => 1], ['A' => 50, 'B' => 51]))->toBe(51);
});

// ---------------------------------------------------------------------------
// Coverage numerator / denominator
// ---------------------------------------------------------------------------

it('reports the populated and applicable weight', function () {
    $weights = ['A' => 30, 'B' => 70, 'C' => 0];
    $scores = ['A' => 10, 'B' => null, 'C' => 10];

    expect(aggregator()->populatedWeight($weights, $scores))->toBe(30.0)
        ->and(aggregator()->applicableWeight($weights))->toBe(100.0);
});
