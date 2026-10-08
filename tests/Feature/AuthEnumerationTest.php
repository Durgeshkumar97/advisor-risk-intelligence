<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(\Tests\TestCase::class, RefreshDatabase::class);

describe('/forgot-password no longer enumerates which emails have an account', function () {

    it('returns byte-identical status messages for an existing account and a non-existent email', function () {
        $existing = User::factory()->create();

        $this->post(route('password.email'), ['email' => $existing->email]);
        $messageForExisting = session('status');

        $this->post(route('password.email'), ['email' => 'nobody-'.uniqid().'@example.com']);
        $messageForNonExistent = session('status');

        expect($messageForExisting)->not->toBeNull();
        expect($messageForExisting)->toBe($messageForNonExistent);
        expect($messageForExisting)->toBe('If an account exists for this email, a password reset link has been sent.');
    });

    it('returns the same message even for a malformed-but-validation-passing edge case', function () {
        // Sanity check: the generic message path is reached via the happy
        // path (email format already validated upstream by $request->validate()),
        // not by swallowing a distinct validation error.
        $response = $this->post(route('password.email'), ['email' => 'someone@example.com']);

        $response->assertSessionHas('status', 'If an account exists for this email, a password reset link has been sent.');
        $response->assertSessionDoesntHaveErrors('email');
    });
});

describe('/login no longer distinguishes a deactivated account from a nonexistent one', function () {

    it('returns the same generic failure message for wrong password, a non-existent email, and a deactivated account', function () {
        $active = User::factory()->create(['password' => bcrypt('correct-password')]);

        $deactivated = User::factory()->create(['password' => bcrypt('correct-password')]);
        $deactivated->delete();

        $this->post(route('login'), [
            'email' => $active->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $wrongPasswordMessage = session('errors')->first('email');

        $this->post(route('login'), [
            'email' => 'nobody-'.uniqid().'@example.com',
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');
        $nonExistentMessage = session('errors')->first('email');

        $this->post(route('login'), [
            'email' => $deactivated->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');
        $deactivatedMessage = session('errors')->first('email');

        expect($wrongPasswordMessage)->toBe('These credentials do not match our records.');
        expect($nonExistentMessage)->toBe($wrongPasswordMessage);
        expect($deactivatedMessage)->toBe($wrongPasswordMessage);
    });

    it('rate-limits repeated attempts against a deactivated account the same way as any other login attempt', function () {
        $deactivated = User::factory()->create();
        $deactivated->delete();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $deactivated->email,
                'password' => 'whatever',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => $deactivated->email,
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toContain('Too many login attempts');
    });

    it('takes the same Timebox-floored response time for a deactivated account as for a wrong password or a nonexistent email', function () {
        // Before the fix, the deactivated-account branch returned before
        // Auth::attempt() ever ran, skipping Laravel's SessionGuard Timebox
        // (a ~200ms floor applied to every failed attempt specifically to
        // prevent timing-based enumeration) — empirically measured at ~5ms
        // vs ~200ms for the other two cases. Same direct microtime()
        // technique used to confirm the bug, not an inspection-based
        // assertion. 100ms is a threshold comfortably above the old ~5ms
        // buggy baseline and comfortably below the 200ms floor, chosen to
        // avoid flakiness while still proving the gap is closed.
        $active = User::factory()->create(['password' => bcrypt('correct-password')]);

        $deactivated = User::factory()->create(['password' => bcrypt('correct-password')]);
        $deactivated->delete();

        $time = function (string $email, string $password): float {
            $start = microtime(true);
            test()->post(route('login'), ['email' => $email, 'password' => $password]);

            return (microtime(true) - $start) * 1000;
        };

        $deactivatedMs = $time($deactivated->email, 'whatever');
        $wrongPasswordMs = $time($active->email, 'wrong-password');
        $nonExistentMs = $time('nobody-'.uniqid().'@example.com', 'whatever');

        expect($deactivatedMs)->toBeGreaterThan(100);
        expect($wrongPasswordMs)->toBeGreaterThan(100);
        expect($nonExistentMs)->toBeGreaterThan(100);
    });
});

describe('/reset-password no longer enumerates which emails have an account', function () {

    // Passes the password policy, so the only thing left to reject the
    // request is the token.
    $newPassword = 'Vq7!mzKp2#Lw9xTd';

    $attempt = fn (string $email, string $token) => test()->post(route('password.store'), [
        'token' => $token,
        'email' => $email,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    it('returns byte-identical errors for a made-up token against an existing account and a non-existent email', function () use ($attempt) {
        // Before the fix these were Laravel's per-status defaults: "This
        // password reset token is invalid." for an account that exists and
        // "We can't find a user with that email address." for one that
        // doesn't — an oracle needing no token at all.
        $existing = User::factory()->create();

        $attempt($existing->email, 'not-a-real-token')->assertSessionHasErrors('email');
        $messageForExisting = session('errors')->first('email');

        $attempt('nobody-'.uniqid().'@example.com', 'not-a-real-token')->assertSessionHasErrors('email');
        $messageForNonExistent = session('errors')->first('email');

        expect($messageForExisting)->toBe($messageForNonExistent);
        expect($messageForExisting)->toBe(NewPasswordController::FAILURE_MESSAGE);
    });

    it('leaves the password untouched when the token is made up', function () use ($attempt, $newPassword) {
        $user = User::factory()->create(['password' => bcrypt('the-original-password')]);

        $attempt($user->email, 'not-a-real-token');

        expect(Hash::check('the-original-password', $user->fresh()->password))->toBeTrue();
        expect(Hash::check($newPassword, $user->fresh()->password))->toBeFalse();
    });

    it('still resets the password when the token is real', function () use ($attempt, $newPassword) {
        // The generic message must only replace the failure path.
        $user = User::factory()->create();

        $attempt($user->email, Password::createToken($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __(Password::PASSWORD_RESET));

        expect(Hash::check($newPassword, $user->fresh()->password))->toBeTrue();
    });
});

describe('/reset-password is throttled on a counter of its own', function () {

    $attempt = fn () => test()->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => 'nobody@example.com',
        'password' => 'Vq7!mzKp2#Lw9xTd',
        'password_confirmation' => 'Vq7!mzKp2#Lw9xTd',
    ]);

    it('allows 10 submissions a minute and refuses the 11th', function () use ($attempt) {
        for ($i = 0; $i < 10; $i++) {
            $attempt()->assertStatus(302);
        }

        $attempt()->assertStatus(429);
    });

    it('is not used up by requests to other throttled routes', function () use ($attempt) {
        // /forgot-password is `throttle:5,1`, and every unnamed throttle
        // shares one counter per IP — so this exhausts that shared counter.
        // A named limiter is keyed separately and must be unaffected.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertStatus(429);

        $attempt()->assertStatus(302);
    });
});
