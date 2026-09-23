<?php

declare(strict_types=1);

use App\Services\IpoRisk\DTOs\GateResult;
use App\Services\IpoRisk\DTOs\Provenance;
use App\Services\IpoRisk\Scoring\GateClamp;

uses(\Tests\TestCase::class);

function gate(string $code, int $floor): GateResult
{
    return new GateResult(
        code: $code,
        floor: $floor,
        evidence: "Evidence for {$code}.",
        provenance: Provenance::of('RHP', 12, new DateTimeImmutable('2026-03-01T00:00:00+00:00')),
    );
}

function clamp(): GateClamp
{
    return new GateClamp;
}

// ---------------------------------------------------------------------------
// I1 — final = max(weighted, highest gate floor)
// ---------------------------------------------------------------------------

it('raises a low weighted score to the gate floor', function () {
    expect(clamp()->apply(30, [gate('GOING_CONCERN', 85)]))->toBe(85);
});

it('leaves a weighted score above the gate floor alone', function () {
    expect(clamp()->apply(90, [gate('NEGATIVE_CFO', 70)]))->toBe(90);
});

it('is a no-op when weighted and floor are equal', function () {
    expect(clamp()->apply(80, [gate('ASM_GSM_FLAGGED', 80)]))->toBe(80);
});

it('returns the weighted score untouched when no gate fired', function () {
    expect(clamp()->apply(42, []))->toBe(42);
});

// ---------------------------------------------------------------------------
// Gates clamp, they never average — with each other or with the weighted mean
// ---------------------------------------------------------------------------

it('takes the highest floor among several fired gates, not their mean', function () {
    $score = clamp()->apply(30, [
        gate('NEGATIVE_CFO', 70),
        gate('GOING_CONCERN', 90),
        gate('SINGLE_YEAR_DISCLOSURE', 65),
    ]);

    expect($score)->toBe(90)
        ->and($score)->not->toBe(75);  // the mean of the three floors
});

it('does not dilute one gate with the weighted mean', function () {
    $score = clamp()->apply(10, [gate('REGULATOR_ACTION', 90)]);

    expect($score)->toBe(90)
        ->and($score)->not->toBe(50);  // the mean of 10 and 90
});

it('names the gate doing the clamping', function () {
    $highest = clamp()->highest([gate('NEGATIVE_CFO', 70), gate('GOING_CONCERN', 90)]);

    expect($highest?->code)->toBe('GOING_CONCERN')
        ->and(clamp()->highestFloor([gate('NEGATIVE_CFO', 70), gate('GOING_CONCERN', 90)]))->toBe(90);
});

it('keeps the first of two gates sharing the highest floor', function () {
    $highest = clamp()->highest([gate('GOING_CONCERN', 90), gate('REGULATOR_ACTION', 90)]);

    expect($highest?->code)->toBe('GOING_CONCERN');
});

// ---------------------------------------------------------------------------
// Unscored edges
// ---------------------------------------------------------------------------

it('stands a gate up even when no weighted factor could be populated', function () {
    expect(clamp()->apply(null, [gate('GOING_CONCERN', 90)]))->toBe(90);
});

it('stays unscored when neither a weighted score nor a gate exists', function () {
    expect(clamp()->apply(null, []))->toBeNull()
        ->and(clamp()->highestFloor([]))->toBeNull()
        ->and(clamp()->highest([]))->toBeNull();
});

it('rejects anything that is not a gate result', function () {
    clamp()->apply(30, ['GOING_CONCERN']);
})->throws(InvalidArgumentException::class, 'expects GateResult');

// ---------------------------------------------------------------------------
// Every configured floor clamps as configured
// ---------------------------------------------------------------------------

it('clamps a floor score up to every configured gate floor', function () {
    foreach (config('ipo_risk.gate_floors') as $code => $floor) {
        expect(clamp()->apply(0, [gate($code, (int) $floor)]))->toBe((int) $floor);
    }
});
