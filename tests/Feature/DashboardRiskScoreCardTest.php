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
