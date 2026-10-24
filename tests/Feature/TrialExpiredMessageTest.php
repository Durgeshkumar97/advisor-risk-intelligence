<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
| CheckSubscription's expired-trial message names the trial's length from the
| plan's trial_days, not a number written into the middleware.
*/

function expiredTrialFor(User $user, int $planId): Subscription
{
    return Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $planId,
        'status' => 'trial',
        'trial_started_at' => now()->subDays(11),
        'trial_ends_at' => now()->subDay(),
    ]);
}

it('names the plan\'s trial_days in the expired-trial message', function () {
    $plan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'price' => '999.00',
        'duration_days' => 30,
        'trial_days' => 10,
        'is_active' => true,
    ]);
    $user = User::factory()->create();
    expiredTrialFor($user, $plan->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('error', 'Your 10-day trial has expired. Choose a plan to continue.');
});

it('says "free trial" when the subscription\'s plan row is gone', function () {
    $user = User::factory()->create();

    // plan_id is a cascading foreign key, so a subscription only outlives its
    // plan as a dangling row (e.g. data restored without the plans table).
    // RefreshDatabase runs each test inside a transaction, where SQLite
    // ignores `foreign_keys = OFF` but honours `defer_foreign_keys`: the
    // check moves to COMMIT, which the rolled-back test never reaches.
    DB::statement('PRAGMA defer_foreign_keys = ON');
    expiredTrialFor($user, 999_999);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('error', 'Your free trial has expired. Choose a plan to continue.');
});
