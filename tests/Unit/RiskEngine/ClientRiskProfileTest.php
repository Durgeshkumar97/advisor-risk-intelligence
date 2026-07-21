<?php

use App\Models\ClientRiskProfile;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Helper — build a ClientRiskProfile without touching the DB
// ---------------------------------------------------------------------------

function makeProfile(float $capacityScore): ClientRiskProfile
{
    return (new ClientRiskProfile())->forceFill([
        'capacity_score' => $capacityScore,
    ]);
}

// ---------------------------------------------------------------------------
// computeCapacityScore() — plain average of the 5 sub-scores
// ---------------------------------------------------------------------------

it('computes the capacity score as the plain average of all five sub-scores', function () {
    // time_horizon=4 (90), income_stability=3 (85), drawdown_reaction=3 (80),
    // emergency_savings=3 (80), primary_goal=4 (90) -> avg = 85.0
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 4,
        incomeStability: 3,
        drawdownReaction: 3,
        emergencySavings: 3,
        primaryGoal: 4,
    );

    expect($score)->toBe(85.0);
});

it('computes the lowest possible capacity score from the most conservative answer set', function () {
    // 10, 20, 10, 15, 15 -> avg = 14.0
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 1,
        incomeStability: 1,
        drawdownReaction: 1,
        emergencySavings: 1,
        primaryGoal: 1,
    );

    expect($score)->toBe(14.0);
});

it('computes a mixed answer set correctly', function () {
    // time_horizon=2 (35), income_stability=2 (55), drawdown_reaction=2 (50),
    // emergency_savings=2 (50), primary_goal=2 (45) -> avg = 47.0
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 2,
        incomeStability: 2,
        drawdownReaction: 2,
        emergencySavings: 2,
        primaryGoal: 2,
    );

    expect($score)->toBe(47.0);
});

// ---------------------------------------------------------------------------
// level() — same LOW/MEDIUM/HIGH thresholds as PortfolioRiskCalculator
// ---------------------------------------------------------------------------

it('labels capacity scores LOW/MEDIUM/HIGH using the same config thresholds as the portfolio score', function () {
    expect(makeProfile(29.99)->level())->toBe('LOW');
    expect(makeProfile(30.0)->level())->toBe('MEDIUM');
    expect(makeProfile(69.99)->level())->toBe('MEDIUM');
    expect(makeProfile(70.0)->level())->toBe('HIGH');
});

// ---------------------------------------------------------------------------
// comparisonMessage() — the three variants + exact boundary cases
// ---------------------------------------------------------------------------

it('reports well-aligned when the gap is within +/-15 points', function () {
    $profile = makeProfile(45.0);

    $message = $profile->comparisonMessage(55.0, 'MEDIUM'); // gap = +10

    expect($message)->toBe(
        'Portfolio risk (55, MEDIUM) is well-aligned with client risk tolerance (45, MEDIUM).'
    );
});

it('reports portfolio risk exceeding client tolerance when the gap is above +15, matching the exact example sentence from the design', function () {
    $profile = makeProfile(45.0);

    $message = $profile->comparisonMessage(78.0, 'HIGH'); // gap = +33

    expect($message)->toBe(
        'Client risk tolerance: 45 (MEDIUM). Portfolio risk: 78 (HIGH). Portfolio risk exceeds client tolerance by 33 points — consider rebalancing toward lower-volatility holdings.'
    );
});

it('reports portfolio risk below client tolerance when the gap is below -15', function () {
    $profile = makeProfile(80.0);

    $message = $profile->comparisonMessage(50.0, 'MEDIUM'); // gap = -30

    expect($message)->toBe(
        'Client risk tolerance: 80 (HIGH). Portfolio risk: 50 (MEDIUM). Portfolio risk is 30 points below client tolerance — there may be room for a more growth-oriented allocation if that fits the client\'s goals.'
    );
});

it('treats a positive gap of exactly 15 as still well-aligned (boundary inclusive)', function () {
    $profile = makeProfile(50.0);

    $message = $profile->comparisonMessage(65.0, 'MEDIUM'); // gap = exactly +15

    expect($message)->toContain('is well-aligned with');
    expect($message)->not->toContain('exceeds client tolerance');
});

it('treats a negative gap of exactly 15 as still well-aligned (boundary inclusive)', function () {
    $profile = makeProfile(65.0);

    $message = $profile->comparisonMessage(50.0, 'MEDIUM'); // gap = exactly -15

    expect($message)->toContain('is well-aligned with');
    expect($message)->not->toContain('below client tolerance');
});

it('treats a positive gap of 16 (one point past the boundary) as exceeding tolerance', function () {
    $profile = makeProfile(50.0);

    $message = $profile->comparisonMessage(66.0, 'MEDIUM'); // gap = +16

    expect($message)->toContain('exceeds client tolerance by 16 points');
});

it('treats a negative gap of 16 (one point past the boundary) as below tolerance', function () {
    $profile = makeProfile(66.0);

    $message = $profile->comparisonMessage(50.0, 'MEDIUM'); // gap = -16

    expect($message)->toContain('is 16 points below client tolerance');
});
