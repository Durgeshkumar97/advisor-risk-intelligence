<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
| Every free trial runs on the Starter plan (StoreIfaTrialLeadAction), so the
| trial length on every pricing card is Starter's trial_days — never a number
| typed into the view, and never another plan's own value.
*/

function planWithTrial(string $slug, int $trialDays): Plan
{
    return Plan::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'price' => '999.00',
        'duration_days' => 30,
        'trial_days' => $trialDays,
        'is_active' => true,
    ]);
}

describe('free-trial length on the pricing cards', function () {

    beforeEach(function () {
        // 10 is neither of the numbers the views used to hard-code (7, 14);
        // Pro's 30 proves no card reads its own plan.
        planWithTrial('starter', 10);
        planWithTrial('pro', 30);
    });

    it('shows Starter\'s trial_days on every home page card', function () {
        $html = $this->get(route('home'))->assertOk()->getContent();

        expect(substr_count($html, '10-day free trial'))->toBe(3)
            ->and($html)->not->toContain('14-day')
            ->and($html)->not->toContain('7-day')
            ->and($html)->not->toContain('30-day free trial');
    });

    it('shows Starter\'s trial_days in the pricing page header and on every card', function () {
        $html = $this->get(route('pricing'))->assertOk()->getContent();

        expect($html)->toContain('Start with a 10-day free trial.')
            ->and(substr_count($html, '10-day free trial'))->toBe(4)
            ->and($html)->not->toContain('14-day')
            ->and($html)->not->toContain('7-day')
            ->and($html)->not->toContain('30-day free trial');
    });
});

describe('with no Starter plan row', function () {

    it('leaves the trial line out of the home page and still renders it', function () {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('day free trial');
    });

    it('leaves the trial line out of the pricing page and still renders it', function () {
        $this->get(route('pricing'))
            ->assertOk()
            ->assertDontSee('day free trial');
    });
});
