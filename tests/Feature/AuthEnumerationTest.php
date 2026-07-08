<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

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
            'email'    => $active->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $wrongPasswordMessage = session('errors')->first('email');

        $this->post(route('login'), [
            'email'    => 'nobody-'.uniqid().'@example.com',
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');
        $nonExistentMessage = session('errors')->first('email');

        $this->post(route('login'), [
            'email'    => $deactivated->email,
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
                'email'    => $deactivated->email,
                'password' => 'whatever',
            ]);
        }

        $response = $this->post(route('login'), [
            'email'    => $deactivated->email,
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toContain('Too many login attempts');
    });
});
