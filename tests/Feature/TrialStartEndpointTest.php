<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * Characterisation tests for POST /api/v1/trial-start.
 *
 * The endpoint promised a 7-day trial but never created a User, and
 * subscriptions.user_id is NOT NULL — so the insert threw rather than merely
 * producing an unusable row. Every first-time caller got a 500.
 *
 * These pin the corrected behaviour: capture the lead (which admin actually
 * consumes), do not write a subscription that could never be honoured, and do
 * not claim a trial that does not exist.
 */
class TrialStartEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_captures_the_lead_without_500ing(): void
    {
        minimalActivePlan();

        $this->postJson(route('api.v1.trial.start'), [
            'name' => 'Rajesh Sharma',
            'phone' => '9876543210',
            'email' => 'rajesh@example.test',
        ])->assertOk();

        $this->assertDatabaseHas('leads', ['phone' => '9876543210']);
    }

    public function test_it_does_not_create_a_subscription_that_grants_nothing(): void
    {
        minimalActivePlan();

        $this->postJson(route('api.v1.trial.start'), [
            'name' => 'Rajesh Sharma',
            'phone' => '9876543210',
        ])->assertOk();

        // A subscription here would have no user_id to hang off, so
        // CheckSubscription (which queries by user_id) could never match it.
        $this->assertSame(0, Subscription::count());
    }

    public function test_it_does_not_promise_a_trial_it_cannot_start(): void
    {
        minimalActivePlan();

        $response = $this->postJson(route('api.v1.trial.start'), [
            'name' => 'Rajesh Sharma',
            'phone' => '9876543210',
        ])->assertOk();

        $this->assertStringNotContainsString('trial started', strtolower($response->json('message')));
    }

    public function test_it_remains_idempotent_for_a_repeated_phone_number(): void
    {
        minimalActivePlan();

        $payload = ['name' => 'Rajesh Sharma', 'phone' => '9876543210'];

        $this->postJson(route('api.v1.trial.start'), $payload)->assertOk();
        $this->postJson(route('api.v1.trial.start'), $payload)->assertOk();

        $this->assertSame(1, Lead::where('phone', '9876543210')->count());
    }

    public function test_it_still_503s_when_the_starter_plan_is_not_seeded(): void
    {
        $this->postJson(route('api.v1.trial.start'), [
            'name' => 'Rajesh Sharma',
            'phone' => '9876543210',
        ])->assertStatus(503);
    }
}
