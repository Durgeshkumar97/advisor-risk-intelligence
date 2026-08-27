<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * The 15-minute admin window used to be enforced by pinning
 * config('session.lifetime') to 15 globally, which applied it to every paying
 * advisor as well. It now lives in session.admin_lifetime and is enforced by
 * the AdminOnly middleware, so the two clocks are independent.
 */
class AdminSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => 1]);
    }

    public function test_the_global_session_lifetime_is_no_longer_pinned_to_15_minutes(): void
    {
        $this->assertSame(120, (int) config('session.lifetime'));
        $this->assertSame(15, (int) config('session.admin_lifetime'));
    }

    public function test_an_active_admin_is_not_logged_out(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // A second request well inside the window still works.
        $this->actingAs($admin)
            ->withSession(['admin_last_activity' => time() - 60])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_an_idle_admin_is_logged_out_and_redirected_to_the_admin_login(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withSession(['admin_last_activity' => time() - (16 * 60)])
            ->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_the_admin_timeout_does_not_apply_to_a_regular_advisor(): void
    {
        // A non-admin never passes through AdminOnly, so a stale
        // admin_last_activity value must not affect them. Their own session is
        // governed by session.lifetime (120), not admin_lifetime.
        $user = activeSubscriberUser();

        $this->actingAs($user)
            ->withSession(['admin_last_activity' => time() - (60 * 60)])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
