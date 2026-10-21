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
// comparisonMessage() — two scores and their signed difference, nothing else
// ---------------------------------------------------------------------------

it('states both scores and a positive difference when portfolio risk is higher', function () {
    $profile = makeProfile(45.0);

    $message = $profile->comparisonMessage(55.0); // difference = +10

    expect($message)->toBe(
        'Portfolio risk score: 55. Client risk tolerance score: 45. Difference: +10 points.'
    );
});

it('states a large positive difference the same way as a small one', function () {
    $profile = makeProfile(45.0);

    $message = $profile->comparisonMessage(78.0); // difference = +33

    expect($message)->toBe(
        'Portfolio risk score: 78. Client risk tolerance score: 45. Difference: +33 points.'
    );
});

it('states a negative difference with a minus sign when portfolio risk is lower', function () {
    $profile = makeProfile(80.0);

    $message = $profile->comparisonMessage(50.0); // difference = -30

    expect($message)->toBe(
        'Portfolio risk score: 50. Client risk tolerance score: 80. Difference: -30 points.'
    );
});

it('states a difference of exactly plus 15 as a number, with no aligned band', function () {
    $profile = makeProfile(50.0);

    $message = $profile->comparisonMessage(65.0);

    expect($message)->toContain('Difference: +15 points.');
    expect($message)->not->toContain('aligned');
});

it('states a difference of exactly minus 15 as a number, with no aligned band', function () {
    $profile = makeProfile(65.0);

    $message = $profile->comparisonMessage(50.0);

    expect($message)->toContain('Difference: -15 points.');
    expect($message)->not->toContain('aligned');
});

it('states a difference of plus 16 in the same sentence shape as plus 15', function () {
    $profile = makeProfile(50.0);

    $message = $profile->comparisonMessage(66.0);

    expect($message)->toBe(
        'Portfolio risk score: 66. Client risk tolerance score: 50. Difference: +16 points.'
    );
});

it('states a difference of minus 16 in the same sentence shape as minus 15', function () {
    $profile = makeProfile(66.0);

    $message = $profile->comparisonMessage(50.0);

    expect($message)->toBe(
        'Portfolio risk score: 50. Client risk tolerance score: 66. Difference: -16 points.'
    );
});

it('states a zero difference without a sign', function () {
    $profile = makeProfile(50.0);

    expect($profile->comparisonMessage(50.0))->toBe(
        'Portfolio risk score: 50. Client risk tolerance score: 50. Difference: 0 points.'
    );
});

it('prints a portfolio score of 69.6 as 70, and takes the difference between the printed numbers so the sentence adds up', function () {
    expect(makeProfile(60.0)->comparisonMessage(69.6))
        ->toContain('Portfolio risk score: 70.');

    // Unrounded: 69.6 - 45.4 = 24.2. Printed: 70 and 45, so +25 — what a
    // reader gets by subtracting the two numbers in front of them.
    expect(makeProfile(45.4)->comparisonMessage(69.6))->toBe(
        'Portfolio risk score: 70. Client risk tolerance score: 45. Difference: +25 points.'
    );
});

it('never passes a verdict on fit, whatever the difference', function () {
    $verdicts = ['aligned', 'suitable', 'appropriate', 'exceeds', 'above', 'below', 'too ', 'should', 'consider', 'recommend', 'review', 'discuss'];

    foreach ([0.0, 20.0, 45.0, 50.0, 80.0, 100.0] as $capacity) {
        foreach ([0.0, 15.0, 50.0, 65.0, 85.0, 100.0] as $portfolio) {
            $message = strtolower(makeProfile($capacity)->comparisonMessage($portfolio));

            foreach ($verdicts as $word) {
                expect($message)->not->toContain($word);
            }
        }
    }
});
