<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

// Tokens are stored hashed, so the raw value can no longer be read back out of
// users.login_token. The admin mint flashes the full link to the session —
// that is where the raw token now lives for assertion purposes.
function rawTokenFromFlashedLink(): string
{
    return \Illuminate\Support\Str::afterLast(session('login_link'), '/');
}

describe('admin_logs is written to on every admin auth / impersonation event', function () {

    it('records admin_login_success when an admin logs in with valid credentials', function () {
        $admin = adminUserFixture();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $log = AdminLog::where('event', 'admin_login_success')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBe($admin->id);
        expect($log->target_user_id)->toBeNull();
        expect($log->ip)->not->toBeNull();
        expect($log->created_at)->not->toBeNull();
    });

    it('records admin_login_failed with the matched user in target_user_id, user_id left null (no admin acted)', function () {
        $admin = adminUserFixture();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $log = AdminLog::where('event', 'admin_login_failed')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($admin->id);
    });

    it('records admin_login_failed with both user_id and target_user_id null when the email does not exist at all', function () {
        $this->post(route('admin.login.submit'), [
            'email' => 'nobody-'.uniqid().'@example.com',
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');

        $log = AdminLog::where('event', 'admin_login_failed')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBeNull();
    });

    it('records admin_login_failed with the matched user in target_user_id even when that admin account is soft-deleted', function () {
        // Auth::attempt() itself already excludes soft-deleted users from
        // authenticating at all (via the model's SoftDeletes scope) — this
        // proves the SEPARATE audit-log lookup uses withTrashed(), so a
        // deactivated admin's failed login attempt is still identifiable
        // instead of being indistinguishable from a nonexistent email.
        $admin = adminUserFixture();
        $admin->delete();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $log = AdminLog::where('event', 'admin_login_failed')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($admin->id);
    });

    it('records admin_login_denied with the rejected non-admin in target_user_id, user_id left null (no admin acted)', function () {
        $user = User::factory()->create([
            'is_admin' => false,
            'password' => bcrypt('correct-password'),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $log = AdminLog::where('event', 'admin_login_denied')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($user->id);
    });

    it('records impersonation_link_minted with both the admin, the target user, and the token hash', function () {
        $admin = adminUserFixture();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.login-link', $target->id))
            ->assertRedirect(route('admin.users.show', $target->id));

        $log = AdminLog::where('event', 'impersonation_link_minted')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBe($admin->id);
        expect($log->target_user_id)->toBe($target->id);
        // users.login_token now stores the SHA-256 itself, so the audit hash
        // and the stored token are the same value.
        expect($log->token_hash)->toBe($target->fresh()->login_token);
        expect($log->token_hash)->toBe(hash('sha256', rawTokenFromFlashedLink()));
    });

    it('records impersonation_link_used against the target user, with a matching token hash, when the magic link is consumed', function () {
        $target = User::factory()->create([
            // Stored hashed, exactly as the mint sites now write it.
            'login_token' => hash('sha256', 'a-valid-token-123'),
            'login_token_expires_at' => now()->addMinutes(15),
            'onboarding_completed' => true,
        ]);

        $this->get(route('auto.login', 'a-valid-token-123'))
            ->assertRedirect(route('dashboard'));

        $log = AdminLog::where('event', 'impersonation_link_used')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($target->id);
        expect($log->token_hash)->toBe(hash('sha256', 'a-valid-token-123'));
    });

    it('lets a mint + use pair be joined by token_hash, and distinguishes a retry mint for the same target', function () {
        $admin = adminUserFixture();
        $target = User::factory()->create(['onboarding_completed' => true]);

        // First mint — e.g. the admin's first attempt, link never actually used.
        $this->actingAs($admin)
            ->post(route('admin.users.login-link', $target->id))
            ->assertRedirect(route('admin.users.show', $target->id));

        $firstMint = AdminLog::where('event', 'impersonation_link_minted')->latest('id')->first();

        // Retry mint — same admin, same target, same 15-minute window, but a
        // fresh random token (overwrites login_token on the target user).
        $this->actingAs($admin)
            ->post(route('admin.users.login-link', $target->id))
            ->assertRedirect(route('admin.users.show', $target->id));

        $secondMint = AdminLog::where('event', 'impersonation_link_minted')->latest('id')->first();
        $secondToken = rawTokenFromFlashedLink();

        expect($secondMint->id)->not->toBe($firstMint->id);
        expect($secondMint->target_user_id)->toBe($firstMint->target_user_id);
        // Same target, same admin, same window — but the hashes must still differ.
        expect($secondMint->token_hash)->not->toBe($firstMint->token_hash);

        // Consuming the link happens as a guest (the auto.login route requires
        // it) — log out the acting admin first, same as a real browser session
        // that isn't authenticated when it follows the link.
        \Illuminate\Support\Facades\Auth::logout();

        // Only the second (currently-valid) token can be consumed.
        $this->get(route('auto.login', $secondToken))
            ->assertRedirect(route('dashboard'));

        $used = AdminLog::where('event', 'impersonation_link_used')->latest('id')->first();

        // The use event joins cleanly to the mint that actually produced it...
        expect($used->token_hash)->toBe($secondMint->token_hash);
        // ...and is unambiguously distinguishable from the earlier, unused mint.
        expect($used->token_hash)->not->toBe($firstMint->token_hash);
    });

    it('does not log impersonation_link_used for an invalid or expired token', function () {
        $this->get(route('auto.login', 'not-a-real-token'))
            ->assertRedirect(route('login'));

        expect(AdminLog::where('event', 'impersonation_link_used')->count())->toBe(0);
    });
});

describe('self-service login links (no admin involved) get their own, distinctly-named audit events', function () {

    it('records self_service_login_link_minted with no admin, the target user, and the token hash', function () {
        $target = User::factory()->create();

        app(\App\Services\UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($target);

        $log = AdminLog::where('event', 'self_service_login_link_minted')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($target->id);
        // The audit hash and the stored token are now the same value.
        expect($log->token_hash)->toBe($target->fresh()->login_token);
    });

    it('records self_service_login_link_used (not impersonation_link_used) when a self-service-minted token is consumed', function () {
        \Illuminate\Support\Facades\Notification::fake();

        $target = User::factory()->create(['onboarding_completed' => true]);

        app(\App\Services\UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($target);

        // The service emails the link; with tokens hashed at rest, that
        // notification is the only place the raw token still exists.
        $captured = null;
        \Illuminate\Support\Facades\Notification::assertSentTo(
            $target,
            \App\Services\Notifications\ExistingAccountLoginLinkNotification::class,
            function ($notification) use (&$captured) {
                $captured = $notification;

                return true;
            }
        );
        $token = \Illuminate\Support\Str::afterLast((fn () => $this->loginUrl)->call($captured), '/');

        $this->get(route('auto.login', $token))
            ->assertRedirect(route('dashboard'));

        $log = AdminLog::where('event', 'self_service_login_link_used')->first();

        expect($log)->not->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->target_user_id)->toBe($target->id);
        expect($log->token_hash)->toBe(hash('sha256', $token));

        // Distinguishable from admin-initiated impersonation by event name
        // alone — not just by checking whether user_id happens to be null.
        expect(AdminLog::where('event', 'impersonation_link_used')->count())->toBe(0);
    });

    it('keeps admin-initiated and self-service mint/use pairs independently queryable by event name', function () {
        $admin = adminUserFixture();
        $adminTarget = User::factory()->create(['onboarding_completed' => true]);
        $selfTarget = User::factory()->create(['onboarding_completed' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.login-link', $adminTarget->id))
            ->assertRedirect(route('admin.users.show', $adminTarget->id));

        app(\App\Services\UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($selfTarget);

        expect(AdminLog::where('event', 'impersonation_link_minted')->count())->toBe(1);
        expect(AdminLog::where('event', 'self_service_login_link_minted')->count())->toBe(1);

        expect(AdminLog::where('event', 'impersonation_link_minted')->first()->target_user_id)
            ->toBe($adminTarget->id);
        expect(AdminLog::where('event', 'self_service_login_link_minted')->first()->target_user_id)
            ->toBe($selfTarget->id);
    });
});

describe('admin_logs rows survive the purge of the target user they reference', function () {

    it('nullifies target_user_id (does not delete the log row) when the target user is force-deleted', function () {
        $admin = adminUserFixture();
        $target = User::factory()->create();

        $log = AdminLog::create([
            'user_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'impersonation_link_minted',
            'ip' => '127.0.0.1',
            'user_agent' => 'PestTest',
        ]);

        $target->forceDelete();

        $log->refresh();

        expect(AdminLog::find($log->id))->not->toBeNull();
        expect($log->target_user_id)->toBeNull();
        expect($log->event)->toBe('impersonation_link_minted');
        expect($log->user_id)->toBe($admin->id);
    });
});
