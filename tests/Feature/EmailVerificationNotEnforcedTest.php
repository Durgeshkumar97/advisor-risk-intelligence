<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * Email verification is sent but not enforced on app routes — deliberately,
 * until control 6 (verify-on-link for checkout / set-password, backfill,
 * notice). Production had no verification before this deploy, so every
 * existing account is unverified, and checkout sends no verification email:
 * enforcing it now would strand a paying customer on /verify-email.
 */
it('lets an unverified user with an active subscription reach the dashboard', function () {
    $user = activeSubscriberUser();
    $user->forceFill(['email_verified_at' => null])->save();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Portfolio Risk Score');
});

it('still sends the verification email on /register', function () {
    Notification::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post(route('register'), [
        'name' => 'New User',
        'email' => 'new-user@example.test',
        'password' => 'Quiet2Orbit!',
        'password_confirmation' => 'Quiet2Orbit!',
        'cf-turnstile-response' => 'test-token',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'new-user@example.test')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
});
