<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\User;
use App\Services\Notifications\ExistingAccountLoginLinkNotification;
use App\Services\UserAccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Magic-link tokens are stored as SHA-256 hashes. The raw token exists only in
 * the emailed/displayed URL, so read access to the users table no longer
 * yields a redeemable credential.
 */
class MagicLinkTokenHashingTest extends TestCase
{
    use RefreshDatabase;

    /** Pull the raw token out of the notification's URL. */
    private function rawTokenFromNotification(User $user): string
    {
        $captured = null;

        Notification::assertSentTo($user, ExistingAccountLoginLinkNotification::class,
            function ($notification) use (&$captured) {
                $captured = $notification;

                return true;
            });

        $url = (fn () => $this->loginUrl)->call($captured);

        return Str::afterLast($url, '/');
    }

    public function test_the_self_service_mint_stores_only_a_hash(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        app(UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($user);

        $raw = $this->rawTokenFromNotification($user);
        $stored = $user->fresh()->login_token;

        $this->assertSame(hash('sha256', $raw), $stored);
        $this->assertNotSame($raw, $stored, 'The raw token must never be persisted.');
        $this->assertSame(64, strlen($stored), 'SHA-256 hex is 64 characters.');
    }

    public function test_the_raw_token_from_the_link_still_logs_the_user_in(): void
    {
        Notification::fake();

        $user = User::factory()->create(['onboarding_completed' => true]);
        app(UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($user);

        $raw = $this->rawTokenFromNotification($user);

        $this->get(route('auto.login', $raw))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Single use: consumed on redemption.
        $this->assertNull($user->fresh()->login_token);
    }

    public function test_the_stored_hash_cannot_itself_be_used_as_a_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        app(UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($user);

        // Someone with database read access holds only this value.
        $stored = $user->fresh()->login_token;

        $this->get(route('auto.login', $stored))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_legacy_plaintext_token_no_longer_authenticates(): void
    {
        $user = User::factory()->create();
        $legacy = Str::random(64);

        // A row as written before hashing: raw token in the column.
        $user->forceFill([
            'login_token' => $legacy,
            'login_token_expires_at' => now()->addMinutes(15),
        ])->save();

        $this->get(route('auto.login', $legacy))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_audit_log_hash_still_matches_the_stored_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        app(UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($user);

        $mint = AdminLog::where('event', 'self_service_login_link_minted')
            ->where('target_user_id', $user->id)
            ->firstOrFail();

        // AdminLog.token_hash already stored a SHA-256; the users column now
        // holds the same value, so the mint-event lookup in the auto.login
        // route still resolves.
        $this->assertSame($user->fresh()->login_token, $mint->token_hash);
    }
}
