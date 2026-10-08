<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClientIntake;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
 * Several flows end by redirecting to /login with a message for the visitor,
 * flashed under whichever key the author reached for: `status`, `success`
 * or `error`. The login view only rendered `status`, so every `success` and
 * `error` message was set, asserted on by the existing tests with
 * assertSessionHas() — and then never shown to anyone.
 *
 * These follow the redirect and assert on the page, because the session
 * holding the message was never what was broken.
 *
 * Each test drives the real route that sends the message rather than
 * planting the flash, so it also fails if a route starts using a key the
 * view does not know about.
 */

function starterPlanForLoginFlash(): Plan
{
    return Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'price' => 0,
        'duration_days' => 30,
        'portfolio_limit' => 1,
        'trial_days' => 14,
        'is_active' => true,
    ]);
}

beforeEach(function () {
    Notification::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
});

describe('error flashes reach the login page', function () {

    it('tells the visitor why a made-up login link did not sign them in', function () {
        $this->followingRedirects()
            ->get(route('auto.login', str_repeat('a', 64)))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('This login link is invalid or has expired. Please use Forgot Password to access your account.');

        $this->assertGuest();
    });

    it('tells the visitor why an expired login link did not sign them in', function () {
        $token = Str::random(64);

        User::factory()->create()->forceFill([
            'login_token' => hash('sha256', $token),
            'login_token_expires_at' => now()->subMinute(),
        ])->save();

        $this->followingRedirects()
            ->get(route('auto.login', $token))
            ->assertViewIs('auth.login')
            ->assertSee('This login link is invalid or has expired.');

        $this->assertGuest();
    });
});

describe('success flashes reach the login page', function () {

    it('shows "you already have a trial" after a duplicate trial submission', function () {
        starterPlanForLoginFlash();

        ClientIntake::query()->create([
            'submission_uuid' => (string) Str::uuid(),
            'name' => 'Existing Advisor',
            'whatsapp' => '+91 90000 00000',
            'email' => 'existing@example.test',
            'firm_name' => 'Existing Firm',
            'status' => 'trial',
        ]);

        $this->followingRedirects()
            ->post(route('ifa.submit'), [
                'advisor_name' => 'Existing Advisor',
                'whatsapp' => '+91 91111 11111',
                'email' => 'existing@example.test',
                'firm_name' => 'Existing Firm',
                'cf-turnstile-response' => 'test-token',
            ])
            ->assertViewIs('auth.login')
            ->assertSee('You already have a trial. Please login to continue.');
    });

    it('shows "we\'ve sent a login link" after a trial submission for an existing account', function () {
        starterPlanForLoginFlash();
        User::factory()->create(['email' => 'has-account@example.test']);

        $this->followingRedirects()
            ->post(route('ifa.submit'), [
                'advisor_name' => 'Someone',
                'whatsapp' => '+91 99999 00000',
                'email' => 'has-account@example.test',
                'firm_name' => 'Some Firm',
                'cf-turnstile-response' => 'test-token',
            ])
            ->assertViewIs('auth.login')
            ->assertSee('An account with this email already exists', false);
    });

    it('shows "we\'ve sent a login link" after registering over a deactivated account', function () {
        User::factory()->create(['email' => 'deactivated@example.test'])->delete();

        $this->followingRedirects()
            ->post(route('register'), [
                'name' => 'Someone',
                'email' => 'deactivated@example.test',
                'password' => 'Vq7!mzKp2#Lw9xTd',
                'password_confirmation' => 'Vq7!mzKp2#Lw9xTd',
                'cf-turnstile-response' => 'test-token',
            ])
            ->assertViewIs('auth.login')
            ->assertSee('An account with this email already exists', false);
    });
});

describe('the status flash still reaches the login page', function () {

    it('confirms a completed password reset', function () {
        // The one key the view already rendered — pinned so that adding the
        // other two cannot have displaced it.
        $user = User::factory()->create();

        $this->followingRedirects()
            ->post(route('password.store'), [
                'token' => Password::createToken($user),
                'email' => $user->email,
                'password' => 'Vq7!mzKp2#Lw9xTd',
                'password_confirmation' => 'Vq7!mzKp2#Lw9xTd',
            ])
            ->assertViewIs('auth.login')
            ->assertSee(__(Password::PASSWORD_RESET));
    });
});

describe('the login page with nothing to say', function () {

    it('renders no alert at all on a plain visit', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('class="alert-status"', false)
            ->assertDontSee('class="alert-error"', false);
    });

    it('escapes a flashed message rather than rendering it as markup', function () {
        $this->withSession(['error' => '<script>alert(1)</script>'])
            ->get(route('login'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<script>alert(1)</script>');
    });
});
