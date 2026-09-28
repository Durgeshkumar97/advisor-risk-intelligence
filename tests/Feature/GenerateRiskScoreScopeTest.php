<?php

use App\Mail\DailyRiskSignalMail;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\RiskScore;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * What risk:generate does and does not write.
 *
 * The command used to create a RiskScore row for every active subscriber every
 * day, holdings or not. An account that never uploaded a file accumulated one
 * meaningless row per day — unbounded, and driven by whoever is registering.
 */
function plan(): Plan
{
    return Plan::create([
        'name' => 'Pro',
        'slug' => 'pro-'.uniqid(),
        'price' => 999,
        'duration_days' => 30,
        'trial_days' => 7,
        'is_active' => true,
    ]);
}

function subscriber(string $status = 'active'): User
{
    $user = User::factory()->create();

    Subscription::create(array_merge(
        [
            'user_id' => $user->id,
            'plan_id' => plan()->id,
            'status' => $status,
        ],
        $status === 'trial'
            ? ['trial_started_at' => now(), 'trial_ends_at' => now()->addDays(7)]
            : ['ends_at' => now()->addDays(30)],
    ));

    return $user;
}

function giveHoldings(User $user): Portfolio
{
    $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

    PortfolioAsset::create([
        'portfolio_id' => $portfolio->id,
        'name' => 'Reliance Industries',
        'asset_type' => 'stock',
        'symbol' => 'RELIANCE',
        'quantity' => 10,
        'buy_price' => 2000,
        'current_price' => 2500,
        'current_value' => 25000,
        'risk_score' => 40,
        'risk_level' => 'MEDIUM',
    ]);

    return $portfolio;
}

// ---------------------------------------------------------------------------
// The fix: no holdings, no row
// ---------------------------------------------------------------------------

it('writes no risk score for a subscriber with no portfolio', function () {
    Mail::fake();

    subscriber();

    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
    Mail::assertNothingSent();
});

it('writes no risk score for a subscriber whose portfolio has no assets', function () {
    Mail::fake();

    $user = subscriber();
    Portfolio::create(['user_id' => $user->id, 'name' => 'Empty']);

    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
    Mail::assertNothingSent();
});

it('writes no row for a trial subscriber with no holdings, which is the bot-signup shape', function () {
    Mail::fake();

    subscriber('trial');

    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
});

it('writes no rows however many times it runs', function () {
    Mail::fake();

    subscriber();
    subscriber('trial');

    $this->artisan('risk:generate')->assertExitCode(0);
    $this->artisan('risk:generate')->assertExitCode(0);
    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
});

it('counts a skipped subscriber as skipped, not sent', function () {
    Mail::fake();

    subscriber();

    $this->artisan('risk:generate')
        ->expectsOutputToContain('Sent: 0 | Skipped: 1 | Failed: 0')
        ->assertExitCode(0);
});

// ---------------------------------------------------------------------------
// Unchanged: a real subscriber with holdings is still scored and emailed
// ---------------------------------------------------------------------------

it('still scores and emails a subscriber with holdings', function () {
    Mail::fake();

    $user = subscriber();
    $portfolio = giveHoldings($user);

    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(1);

    $score = RiskScore::first();

    expect($score->user_id)->toBe($user->id)
        ->and($score->portfolio_id)->toBe($portfolio->id)
        ->and($score->meta['trigger'])->toBe('daily-cron')
        ->and($score->meta['has_holdings'])->toBeTrue();

    Mail::assertSent(DailyRiskSignalMail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('scores only the subscribers that have holdings', function () {
    Mail::fake();

    $withHoldings = subscriber();
    giveHoldings($withHoldings);

    subscriber();          // no portfolio
    subscriber('trial');   // no portfolio

    $this->artisan('risk:generate')
        ->expectsOutputToContain('Sent: 1 | Skipped: 2 | Failed: 0')
        ->assertExitCode(0);

    expect(RiskScore::count())->toBe(1)
        ->and(RiskScore::first()->user_id)->toBe($withHoldings->id);

    Mail::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Unchanged: subscription scope still gates who is considered at all
// ---------------------------------------------------------------------------

it('ignores a subscriber whose subscription has expired', function () {
    Mail::fake();

    $user = User::factory()->create();
    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => plan()->id,
        'status' => 'active',
        'ends_at' => now()->subDay(),
    ]);
    giveHoldings($user);

    $this->artisan('risk:generate')->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
    Mail::assertNothingSent();
});

it('exits cleanly when nobody is subscribed', function () {
    Mail::fake();

    $this->artisan('risk:generate')
        ->expectsOutputToContain('No active subscribers')
        ->assertExitCode(0);

    expect(RiskScore::count())->toBe(0);
});
