<?php

declare(strict_types=1);

use App\Services\IpoRisk\Enums\Actionability;
use App\Services\IpoRisk\Enums\CoverageBand;
use App\Services\IpoRisk\Enums\FactorFamily;
use App\Services\IpoRisk\Enums\IpoState;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// I6 — actionability derives from state, and from nothing else
// ---------------------------------------------------------------------------

it('maps every state to its actionability', function (IpoState $state, Actionability $expected) {
    expect($state->actionability())->toBe($expected);
})->with([
    'pre-issue' => [IpoState::PRE_ISSUE, Actionability::NOT_YET_COMMITTED],
    'allotted, unlisted' => [IpoState::ALLOTTED_UNLISTED, Actionability::NO_EXIT_AVAILABLE],
    'listed and held' => [IpoState::LISTED_HELD, Actionability::EXIT_AVAILABLE],
]);

it('covers every state, so a new one cannot be added without a mapping', function () {
    foreach (IpoState::cases() as $state) {
        expect($state->actionability())->toBeInstanceOf(Actionability::class);
    }
});

it('uses every actionability case exactly once', function () {
    $mapped = array_map(
        static fn (IpoState $state): Actionability => $state->actionability(),
        IpoState::cases(),
    );

    expect($mapped)->toHaveCount(count(Actionability::cases()))
        ->and(array_unique(array_column(
            array_map(static fn (Actionability $a): array => ['v' => $a->value], $mapped),
            'v',
        )))->toHaveCount(count(Actionability::cases()));
});

// ---------------------------------------------------------------------------
// Family sets
// ---------------------------------------------------------------------------

it('scores pre-issue and allotted-unlisted off the same family set', function () {
    expect(IpoState::PRE_ISSUE->familyGroup())
        ->toBe(IpoState::ALLOTTED_UNLISTED->familyGroup())
        ->toBe('pre_listing');
});

it('scores a listed holding off the listed family set', function () {
    expect(IpoState::LISTED_HELD->familyGroup())->toBe('listed');
});

it('returns six weighted families for every state', function (IpoState $state) {
    $families = FactorFamily::forState($state);

    expect($families)->toHaveCount(6);

    foreach ($families as $family) {
        expect($family->group())->toBe($state->familyGroup())
            ->and($family->weight())->toBeGreaterThan(0);
    }
})->with([
    [IpoState::PRE_ISSUE],
    [IpoState::ALLOTTED_UNLISTED],
    [IpoState::LISTED_HELD],
]);

it('weights each state family set to exactly 100', function (IpoState $state) {
    $total = array_sum(array_map(
        static fn (FactorFamily $f): int => $f->weight(),
        FactorFamily::forState($state),
    ));

    expect($total)->toBe(100);
})->with([
    [IpoState::PRE_ISSUE],
    [IpoState::ALLOTTED_UNLISTED],
    [IpoState::LISTED_HELD],
]);

it('assigns every family to exactly one group', function () {
    foreach (FactorFamily::cases() as $family) {
        expect($family->group())->toBeIn(['pre_listing', 'listed']);
    }
});

// ---------------------------------------------------------------------------
// Labels exist for every case, on every enum
// ---------------------------------------------------------------------------

it('labels every enum case', function () {
    $cases = array_merge(
        IpoState::cases(),
        Actionability::cases(),
        CoverageBand::cases(),
        FactorFamily::cases(),
    );

    foreach ($cases as $case) {
        expect($case->label())->toBeString()->not->toBe('');
    }
});
