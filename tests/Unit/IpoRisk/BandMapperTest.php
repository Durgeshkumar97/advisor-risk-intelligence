<?php

declare(strict_types=1);

use App\Models\RiskScore;
use App\Services\IpoRisk\DTOs\IpoRiskResult;
use App\Services\IpoRisk\Enums\CoverageBand;
use App\Services\IpoRisk\Enums\IpoState;
use App\Services\IpoRisk\Scoring\BandMapper;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// D2 — one risk-band mapping in the product, and this module borrows it
// ---------------------------------------------------------------------------

it('agrees with RiskScore::levelFromScore on every integer score', function () {
    foreach (range(0, 100) as $score) {
        expect(BandMapper::riskLevel($score))->toBe(RiskScore::levelFromScore((float) $score));
    }
});

it('agrees with RiskScore::levelFromScore on every integer score inside a result', function () {
    foreach (range(0, 100) as $score) {
        $result = new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: $score, coverageScore: 90);

        expect($result->riskLevel)->toBe(RiskScore::levelFromScore((float) $score));
    }
});

it('follows the shared thresholds when they are overridden', function () {
    config()->set('risk.low_threshold', 20);
    config()->set('risk.high_threshold', 55);

    foreach (range(0, 100) as $score) {
        expect(BandMapper::riskLevel($score))->toBe(RiskScore::levelFromScore((float) $score));
    }

    expect(BandMapper::riskLevel(19))->toBe('LOW')
        ->and(BandMapper::riskLevel(20))->toBe('MEDIUM')
        ->and(BandMapper::riskLevel(54))->toBe('MEDIUM')
        ->and(BandMapper::riskLevel(55))->toBe('HIGH');
});

it('lands on the documented boundaries at the default thresholds', function () {
    expect(BandMapper::riskLevel(29))->toBe('LOW')
        ->and(BandMapper::riskLevel(30))->toBe('MEDIUM')
        ->and(BandMapper::riskLevel(69))->toBe('MEDIUM')
        ->and(BandMapper::riskLevel(70))->toBe('HIGH');
});

it('has no risk level for an unscored result', function () {
    expect(BandMapper::riskLevel(null))->toBeNull();
});

// ---------------------------------------------------------------------------
// Coverage bands
// ---------------------------------------------------------------------------

it('maps a coverage score to its band', function (int $score, CoverageBand $expected) {
    expect(BandMapper::coverageBand($score))->toBe($expected)
        ->and(CoverageBand::fromScore($score))->toBe($expected);
})->with([
    'top' => [100, CoverageBand::HIGH],
    'high edge' => [80, CoverageBand::HIGH],
    'just under high' => [79, CoverageBand::MODERATE],
    'moderate edge' => [60, CoverageBand::MODERATE],
    'just under moderate' => [59, CoverageBand::LIMITED],
    'limited edge' => [40, CoverageBand::LIMITED],
    'just under limited' => [39, CoverageBand::INSUFFICIENT],
    'bottom' => [0, CoverageBand::INSUFFICIENT],
]);

it('treats an absent coverage score as insufficient, not unknown', function () {
    expect(BandMapper::coverageBand(null))->toBe(CoverageBand::INSUFFICIENT);
});

it('covers all four coverage bands across 0-100 with no gaps', function () {
    $seen = [];

    foreach (range(0, 100) as $score) {
        $seen[BandMapper::coverageBand($score)->value] = true;
    }

    expect(array_keys($seen))->toEqualCanonicalizing(['high', 'moderate', 'limited', 'insufficient']);
});

it('follows the configured coverage band edges', function () {
    config()->set('ipo_risk.coverage.bands', ['high' => 90, 'moderate' => 70, 'limited' => 50]);

    expect(BandMapper::coverageBand(89))->toBe(CoverageBand::MODERATE)
        ->and(BandMapper::coverageBand(90))->toBe(CoverageBand::HIGH)
        ->and(BandMapper::coverageBand(49))->toBe(CoverageBand::INSUFFICIENT);
});

it('never lets the band disagree with the score inside a result', function (int $coverage) {
    $result = new IpoRiskResult(state: IpoState::LISTED_HELD, riskScore: null, coverageScore: $coverage);

    expect($result->coverageBand)->toBe(BandMapper::coverageBand($coverage));
})->with([0, 39, 40, 59, 60, 79, 80, 100]);
