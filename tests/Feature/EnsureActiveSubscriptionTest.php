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

// ─── file-level helpers ───────────────────────────────────────────────────────

/** Minimal Plan row — only the fields the migration requires non-null. */
function activeSubTestPlan(): Plan
{
    return Plan::create([
        'name'          => 'Starter',
        'slug'          => 'starter',
        'price'         => '999.00',
        'duration_days' => 30,
        'is_active'     => true,
    ]);
}

/** PortfolioFile owned by $user with a report + bundle already sitting on disk. */
function fileWithReportsFor(User $user, Portfolio $portfolio): PortfolioFile
{
    $originalPath = "uploads/{$user->id}/portfolio.csv";
    $reportPath   = "reports/{$user->id}/report.pdf";
    $bundlePath   = "reports/{$user->id}/bundle.zip";

    Storage::disk('portfolios')->put($originalPath, 'name,asset_type,symbol,current_value');
    Storage::disk('portfolios')->put($reportPath, 'pdf-bytes');
    Storage::disk('portfolios')->put($bundlePath, 'zip-bytes');

    return PortfolioFile::create([
        'user_id'            => $user->id,
        'portfolio_id'       => $portfolio->id,
        'original_name'      => 'portfolio.csv',
        'stored_name'        => 'portfolio.csv',
        'path'               => $originalPath,
        'report_path'        => $reportPath,
        'bundle_report_path' => $bundlePath,
        'mime_type'          => 'text/csv',
        'file_size'          => 100,
        'status'             => PortfolioFile::STATUS_PROCESSED,
    ]);
}

/** Subscription for $user, with the given status/ends_at. */
function subscriptionWith(User $user, Plan $plan, string $status, $endsAt): Subscription
{
    return Subscription::create([
        'user_id'    => $user->id,
        'plan_id'    => $plan->id,
        'status'     => $status,
        'starts_at'  => now()->subDays(10),
        'ends_at'    => $endsAt,
        'renewal_at' => $endsAt,
        'provider'   => 'razorpay',
    ]);
}

beforeEach(function () {
    Storage::fake('portfolios');
    $this->plan = activeSubTestPlan();
});

describe('EnsureActiveSubscription (active.sub) gates report.download and file.bundle.download', function () {

    it('allows a genuinely active subscription to download the report and the bundle', function () {
        $user      = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'P1']);
        $file      = fileWithReportsFor($user, $portfolio);

        subscriptionWith($user, $this->plan, 'active', now()->addDays(20));

        $this->actingAs($user)->get(route('report.download', $file->id))->assertOk();
        $this->actingAs($user)->get(route('file.bundle.download', $file->id))->assertOk();
    });

    it('denies a subscription in its grace period on report.download and file.bundle.download', function () {
        $user      = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'P1']);
        $file      = fileWithReportsFor($user, $portfolio);

        // status=active but ends_at already 1 day in the past — squarely inside
        // the 3-day grace window that CheckSubscription (and the old, buggy
        // EnsureActiveSubscription) would allow through.
        subscriptionWith($user, $this->plan, 'active', now()->subDay());

        $this->actingAs($user)->get(route('report.download', $file->id))->assertRedirect('/pricing');
        $this->actingAs($user)->get(route('file.bundle.download', $file->id))->assertRedirect('/pricing');
    });

    it('still allows that same grace-period subscription to view the dashboard, file, and report', function () {
        $user      = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'P1']);
        $file      = fileWithReportsFor($user, $portfolio);

        subscriptionWith($user, $this->plan, 'active', now()->subDay());

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('file.view', $file->id))->assertOk();
        $this->actingAs($user)->get(route('report.view', $file->id))->assertOk();
    });
});
