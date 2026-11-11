<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

/*
| Cancelling is a statement of intent, not a revocation. Plans are one-off
| 30-day purchases with no auto-debit, and the UI promises "you retain access
| until {date}" — so a cancelled plan keeps every entitlement it had until its
| own end date. Revocation (a refund) is a different path: the refund webhook
| sets status 'cancelled' and backdates ends_at, and is covered in
| PaymentFlowTest.
*/

/** A processed upload for $user with its report and bundle already on disk. */
function cancelTestFileFor(User $user): PortfolioFile
{
    $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

    Storage::disk('portfolios')->put("reports/{$user->id}/report.pdf", 'pdf-bytes');
    Storage::disk('portfolios')->put("reports/{$user->id}/bundle.zip", 'zip-bytes');

    return PortfolioFile::create([
        'user_id' => $user->id,
        'portfolio_id' => $portfolio->id,
        'original_name' => 'client-a.csv',
        'stored_name' => 'client-a.csv',
        'path' => "uploads/{$user->id}/client-a.csv",
        'report_path' => "reports/{$user->id}/report.pdf",
        'bundle_report_path' => "reports/{$user->id}/bundle.zip",
        'mime_type' => 'text/csv',
        'file_size' => 100,
        'status' => PortfolioFile::STATUS_PROCESSED,
    ]);
}

function paidSubscriptionFor(User $user, Plan $plan, int $daysLeft = 20): Subscription
{
    return Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(30 - $daysLeft),
        'ends_at' => now()->addDays($daysLeft),
        'renewal_at' => now()->addDays($daysLeft),
        'provider' => 'razorpay',
        'provider_subscription_id' => 'pay_cancel_'.$user->id,
    ]);
}

function trialSubscriptionFor(User $user, Plan $plan, int $daysLeft = 10): Subscription
{
    return Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'trial',
        'trial_started_at' => now()->subDays(4),
        'trial_ends_at' => now()->addDays($daysLeft),
    ]);
}

beforeEach(function () {
    Storage::fake('portfolios');
    $this->plan = minimalActivePlan();
});

describe('cancelling a paid plan', function () {

    it('keeps report and bundle downloads working until the plan ends', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        $file = cancelTestFileFor($user);
        $subscription = paidSubscriptionFor($user, $this->plan);

        $this->actingAs($user)->delete(route('subscription.cancel'))
            ->assertRedirect(route('subscription.index'))
            ->assertSessionHas('success');

        $this->actingAs($user)->get(route('report.download', $file->id))->assertOk();
        $this->actingAs($user)->get(route('file.bundle.download', $file->id))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        expect($subscription->fresh()->isCancelled())->toBeTrue();
    });

    it('says "Access until" with the plan end date on the subscription page', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        $subscription = paidSubscriptionFor($user, $this->plan);

        $this->actingAs($user)->delete(route('subscription.cancel'));

        $date = $subscription->ends_at->format('d M Y');

        $this->actingAs($user)->get(route('subscription.index'))
            ->assertOk()
            ->assertSee("You retain access until {$date}.")
            ->assertSee('Access until');
    });

    it('ends access once the plan end date has passed', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        $file = cancelTestFileFor($user);
        paidSubscriptionFor($user, $this->plan, daysLeft: 2);

        $this->actingAs($user)->delete(route('subscription.cancel'));

        $this->travel(3)->days();
        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->actingAs($user)->get(route('report.download', $file->id))->assertRedirect('/pricing');
    });

    it('refuses to cancel the same plan twice', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        paidSubscriptionFor($user, $this->plan);

        $this->actingAs($user)->delete(route('subscription.cancel'))->assertSessionHas('success');
        $this->actingAs($user)->delete(route('subscription.cancel'))->assertSessionHas('error');
    });
});

describe('cancelling a trial', function () {

    it('keeps dashboard access until the trial end date', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        $subscription = trialSubscriptionFor($user, $this->plan);

        $this->actingAs($user)->delete(route('subscription.cancel'))->assertSessionHas('success');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('portfolio.upload'))->assertOk();

        expect($subscription->fresh()->isCancelled())->toBeTrue();
    });

    it('names the trial end date on the subscription page and dashboard, not a blank date', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        $subscription = trialSubscriptionFor($user, $this->plan);

        $date = $subscription->trial_ends_at->format('d M Y');

        $this->actingAs($user)->delete(route('subscription.cancel'))
            ->assertSessionHas('success', "Your subscription has been cancelled. You retain access until {$date}.");

        $this->actingAs($user)->get(route('subscription.index'))
            ->assertSee("You retain access until {$date}.");

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee("you retain access until {$date}.");
    });

    it('still ends access when the trial ends', function () {
        $user = User::factory()->create(['onboarding_completed' => true]);
        trialSubscriptionFor($user, $this->plan, daysLeft: 1);

        $this->actingAs($user)->delete(route('subscription.cancel'));

        $this->travel(2)->days();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('subscription.index'));
    });
});
