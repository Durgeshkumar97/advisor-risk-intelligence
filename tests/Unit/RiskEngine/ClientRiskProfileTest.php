<?php

use App\Models\ClientRiskProfile;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Helper — build a ClientRiskProfile without touching the DB
// ---------------------------------------------------------------------------

function makeProfile(float $capacityScore): ClientRiskProfile
{
    return (new ClientRiskProfile)->forceFill([
        'capacity_score' => $capacityScore,
    ]);
}

// ---------------------------------------------------------------------------
// computeCapacityScore() — plain average of the 5 sub-scores
// ---------------------------------------------------------------------------

it('computes the capacity score as the plain average of all five sub-scores', function () {
    // time_horizon=4 (100), income_stability=3 (100), drawdown_reaction=3 (70),
    // emergency_savings=3 (100), primary_goal=4 (100) -> avg = 94.0
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 4,
        incomeStability: 3,
        drawdownReaction: 3,
        emergencySavings: 3,
        primaryGoal: 4,
    );

    expect($score)->toBe(94.0);
});

it('computes a genuine 0 as the lowest possible capacity score (bottom of the 0-100 scale)', function () {
    // Every question's option 1 now scores 0 -> avg = 0.0, matching the bottom
    // of PortfolioRiskCalculator's range.
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 1,
        incomeStability: 1,
        drawdownReaction: 1,
        emergencySavings: 1,
        primaryGoal: 1,
    );

    expect($score)->toBe(0.0);
});

it('computes a genuine 100 as the highest possible capacity score (top of the 0-100 scale)', function () {
    // Every question's top option now scores 100 -> avg = 100.0. Proves the
    // rescaled instrument spans the full 0-100, so capacity_score is directly
    // comparable to the portfolio score across the whole range.
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 4,
        incomeStability: 3,
        drawdownReaction: 4,
        emergencySavings: 3,
        primaryGoal: 4,
    );

    expect($score)->toBe(100.0);
});

it('computes a mixed answer set correctly', function () {
    // time_horizon=2 (30), income_stability=2 (50), drawdown_reaction=2 (30),
    // emergency_savings=2 (50), primary_goal=2 (30) -> avg = 38.0
    $score = ClientRiskProfile::computeCapacityScore(
        timeHorizon: 2,
        incomeStability: 2,
        drawdownReaction: 2,
        emergencySavings: 2,
        primaryGoal: 2,
    );

    expect($score)->toBe(38.0);
});

it('throws on an out-of-range option index instead of silently scoring it as 0', function () {
    // A silent 0 (from a missing array key -> null) would skew a client's risk
    // score downward with no error; computeCapacityScore() must fail loudly.
    expect(fn () => ClientRiskProfile::computeCapacityScore(
        timeHorizon: 5, // only 1-4 are valid for this question
        incomeStability: 3,
        drawdownReaction: 3,
        emergencySavings: 3,
        primaryGoal: 4,
    ))->toThrow(InvalidArgumentException::class);
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

// ---------------------------------------------------------------------------
// F7 — documented, inherited display quirk (NOT a bug). Pinned so a future
// reviewer does not re-discover and re-litigate it.
// ---------------------------------------------------------------------------

it('prints a portfolio score of 69.6 as "70 (MEDIUM)" — the number is rounded for display while the level comes from the unrounded score (<70 = MEDIUM). This is known, pre-existing behavior shared with the report summary table (risk-report.blade.php), not a new bug.', function () {
    // The level string is supplied by the caller as $riskScore->level(), which
    // reads the UNROUNDED score: 69.6 < 70 -> MEDIUM. The sentence then prints
    // number_format(69.6, 0) = "70" next to it. So "70" can appear labelled
    // MEDIUM even though 70 on its own reads as HIGH per config/risk.php.
    $profile = makeProfile(60.0);

    // gap = 69.6 - 60 = 9.6 -> within +/-15 -> the aligned sentence, which
    // prints the portfolio number and level side by side.
    $message = $profile->comparisonMessage(69.6, 'MEDIUM');

    expect($message)->toContain('Portfolio risk (70, MEDIUM)');
});
