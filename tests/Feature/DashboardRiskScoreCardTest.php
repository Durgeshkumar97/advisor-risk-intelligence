<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RiskScore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * The dashboard's Portfolio Risk Score card when there is no score to show.
 *
 * risk:generate no longer writes a row for a user with no holdings, so the
 * cron will never produce a first score for them on its own. The empty state
 * used to promise one at "8:00 AM tomorrow" — true only while the cron kept
 * writing a meaningless row every day. It now states the only thing that
 * actually produces a score.
 */
it('tells a user with no score to upload, not to wait for tomorrow', function () {
    $user = activeSubscriberUser();

    expect(RiskScore::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upload a portfolio to receive your first risk score.')
        ->assertDontSee('8:00 AM')
        ->assertDontSee('will be generated at');
});

// ---------------------------------------------------------------------------
// Old no-holdings rows are hidden; real scores are not
// ---------------------------------------------------------------------------

/**
 * A RiskScore row at a fixed time. $meta is passed through exactly, so a test
 * can distinguish "has_holdings => false" from the key being absent.
 */
function scoreRow(int $userId, float $score, array $meta, string $at): RiskScore
{
    $row = RiskScore::create([
        'user_id' => $userId,
        'score' => $score,
        'volatility' => 0,
        'drawdown' => 0,
        'meta' => $meta,
        'generated_at' => $at,
    ]);

    // created_at drives ->latest(); set it explicitly so ordering is not
    // left to insertion speed.
    $row->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $row;
}

it('hides a row explicitly marked has_holdings = false', function () {
    $user = activeSubscriberUser();

    // What risk:generate used to write daily for an account with no holdings.
    scoreRow($user->id, 0.0, ['has_holdings' => false, 'trigger' => 'daily-cron'], '2026-09-20 08:00:00');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upload a portfolio to receive your first risk score.')
        ->assertDontSee('LOW RISK');
});

it('still shows a row with no has_holdings key, which is every upload-time score', function () {
    $user = activeSubscriberUser();

    // ProcessPortfolioFile never sets has_holdings.
    scoreRow($user->id, 72.0, ['trigger' => 'file_upload'], '2026-09-20 08:00:00');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('HIGH RISK')
        ->assertDontSee('Upload a portfolio to receive your first risk score.');
});

it('still shows a row marked has_holdings = true', function () {
    $user = activeSubscriberUser();

    scoreRow($user->id, 45.0, ['has_holdings' => true, 'trigger' => 'daily-cron'], '2026-09-20 08:00:00');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('MEDIUM RISK')
        ->assertDontSee('Upload a portfolio to receive your first risk score.');
});

it('shows the latest real score when a newer no-holdings row sits on top of it', function () {
    $user = activeSubscriberUser();

    scoreRow($user->id, 72.0, ['trigger' => 'file_upload'], '2026-09-18 10:00:00');
    scoreRow($user->id, 0.0, ['has_holdings' => false, 'trigger' => 'daily-cron'], '2026-09-20 08:00:00');

    // The filter is in the query: the latest ELIGIBLE row wins. Filtering after
    // first() would have picked the newer false row and then shown nothing.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('HIGH RISK')
        ->assertDontSee('LOW RISK')
        ->assertDontSee('Upload a portfolio to receive your first risk score.');
});
