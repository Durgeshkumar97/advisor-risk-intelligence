<?php

declare(strict_types=1);

use App\Services\IpoRisk\RulesetHasher;

uses(\Tests\TestCase::class);

function hasher(): RulesetHasher
{
    return new RulesetHasher;
}

// ---------------------------------------------------------------------------
// I9 — same config, same hash; any threshold change, different hash; key
//      order, same hash.
// ---------------------------------------------------------------------------

it('produces the same hash for the same config', function () {
    expect(hasher()->hash())->toBe(hasher()->hash());
});

it('produces a 64-character hex sha256', function () {
    expect(hasher()->hash())->toMatch('/^[0-9a-f]{64}$/');
});

it('ignores key order', function () {
    $a = ['families' => ['pre_listing' => ['governance' => 20, 'valuation' => 12]], 'gate_floors' => ['GOING_CONCERN' => 90]];
    $b = ['gate_floors' => ['GOING_CONCERN' => 90], 'families' => ['pre_listing' => ['valuation' => 12, 'governance' => 20]]];

    expect(hasher()->hashOf($a))->toBe(hasher()->hashOf($b));
});

it('does not confuse numeric-looking keys when sorting', function () {
    $a = ['10' => 'a', '9' => 'b', '100' => 'c'];
    $b = ['100' => 'c', '10' => 'a', '9' => 'b'];

    expect(hasher()->hashOf($a))->toBe(hasher()->hashOf($b));
});

it('preserves list order, because a list order carries meaning', function () {
    $a = ['prohibited_terms' => ['buy', 'sell']];
    $b = ['prohibited_terms' => ['sell', 'buy']];

    expect(hasher()->hashOf($a))->not->toBe(hasher()->hashOf($b));
});

it('changes when a single family weight changes', function () {
    $before = hasher()->hash();

    config()->set('ipo_risk.families.pre_listing.valuation', 13);

    expect(hasher()->hash())->not->toBe($before);
});

it('changes when a single gate floor changes', function () {
    $before = hasher()->hash();

    config()->set('ipo_risk.gate_floors.NEGATIVE_CFO', 71);

    expect(hasher()->hash())->not->toBe($before);
});

it('changes when a single coverage penalty changes', function () {
    $before = hasher()->hash();

    config()->set('ipo_risk.coverage.penalties.ocr_source', -11);

    expect(hasher()->hash())->not->toBe($before);
});

// ---------------------------------------------------------------------------
// D2 — the module borrows its risk-level edges from config/risk.php, so an
//      env override there has to move the hash too.
// ---------------------------------------------------------------------------

it('folds the borrowed risk-level thresholds into the ruleset', function () {
    expect(hasher()->ruleset())
        ->toHaveKey(RulesetHasher::BOUND_KEY_SLOT)
        ->and(hasher()->ruleset()[RulesetHasher::BOUND_KEY_SLOT])
        ->toHaveKeys(['risk.low_threshold', 'risk.high_threshold']);
});

it('changes when the borrowed low threshold is overridden', function () {
    $before = hasher()->hash();

    config()->set('risk.low_threshold', 35);

    expect(hasher()->hash())->not->toBe($before);
});

it('changes when the borrowed high threshold is overridden', function () {
    $before = hasher()->hash();

    config()->set('risk.high_threshold', 75);

    expect(hasher()->hash())->not->toBe($before);
});
