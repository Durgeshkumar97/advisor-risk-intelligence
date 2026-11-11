<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

/*
| Plans are one-off 30-day purchases with no auto-debit. Nothing a paying
| advisor sees may say the plan "renews" on a date; it says when access ends.
| ("Renew" as a button the advisor chooses to press is fine and stays.)
*/

beforeEach(function () {
    $this->plan = minimalActivePlan();
    // checkout.show only accepts these slugs.
    $this->plan->update(['slug' => 'starter']);
});

function activePlanFor(User $user, Plan $plan): Subscription
{
    return Subscription::create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
        'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25),
        'renewal_at' => now()->addDays(25), 'provider' => 'razorpay',
    ]);
}

it('says "Access until" with the end date on the subscription page', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $subscription = activePlanFor($user, $this->plan);

    $this->actingAs($user)->get(route('subscription.index'))
        ->assertOk()
        ->assertSeeInOrder(['Access until', $subscription->ends_at->format('d M Y')])
        ->assertDontSee('Renews on');
});

it('shows a trial its end date, labelled as such, instead of a blank "Renews on"', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $trial = Subscription::create([
        'user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'trial',
        'trial_started_at' => now()->subDay(), 'trial_ends_at' => now()->addDays(13),
    ]);

    $this->actingAs($user)->get(route('subscription.index'))
        ->assertOk()
        ->assertSeeInOrder(['Trial ends', $trial->trial_ends_at->format('d M Y')])
        ->assertDontSee('Renews on');
});

it('says "Access until" on the already-subscribed checkout page', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $subscription = activePlanFor($user, $this->plan);

    $this->actingAs($user)->get(route('checkout.show', 'starter'))
        ->assertOk()
        ->assertSeeInOrder(['Access until:', $subscription->ends_at->format('d M Y')])
        ->assertDontSee('Renews / expires');
});
