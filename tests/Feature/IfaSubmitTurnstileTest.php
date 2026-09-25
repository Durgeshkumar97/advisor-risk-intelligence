<?php

namespace Tests\Feature;

use App\Mail\AdminFreeTrialLeadSubmittedMail;
use App\Models\ClientIntake;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * /ifa-submit creates a User and grants a trial subscription from an
 * unauthenticated form. Until this change it had no captcha, and every one of
 * the 72 bot accounts created in September 2026 came through it (production,
 * 2026-09-25: 72 total, 72 via /ifa-submit, 71 trial, 0 verified).
 *
 * The property that matters: a request that fails verification creates
 * nothing and notifies nobody.
 */
function starterPlanForTrial(): Plan
{
    return Plan::query()->create([
        'name' => 'Starter',
        'slug' => 'starter',
        'price' => 0,
        'duration_days' => 30,
        'portfolio_limit' => 1,
        'trial_days' => 14,
        'is_active' => true,
    ]);
}

function trialForm(array $overrides = []): array
{
    return array_merge([
        'advisor_name' => 'Bot McBotface',
        'whatsapp' => '+91 90000 12345',
        'email' => 'bot@example.test',
        'firm_name' => 'Bot Capital',
    ], $overrides);
}

/** Nothing the trial path writes may exist. */
function assertTrialPathWroteNothing(): void
{
    expect(User::count())->toBe(0)
        ->and(Subscription::count())->toBe(0)
        ->and(ClientIntake::count())->toBe(0)
        ->and(Portfolio::count())->toBe(0);

    Mail::assertNothingQueued();
}

beforeEach(function () {
    Mail::fake();
    starterPlanForTrial();
    config()->set('risksignal.lead_notifications.admin_email', 'owner@risksignal.test');
});

// ---------------------------------------------------------------------------
// Rejections — fail closed, write nothing
// ---------------------------------------------------------------------------

it('rejects a submission with no Turnstile token and creates nothing', function () {
    Http::fake();

    $this->from(route('home'))
        ->post(route('ifa.submit'), trialForm())
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors(TurnstileVerifier::FIELD);

    assertTrialPathWroteNothing();

    // Rejected by validation before Cloudflare is ever asked.
    Http::assertNothingSent();
});

it('rejects a token Cloudflare says is invalid and creates nothing', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->from(route('home'))
        ->post(route('ifa.submit'), trialForm(['cf-turnstile-response' => 'forged']))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors([TurnstileVerifier::FIELD => 'Captcha verification failed. Please try again.']);

    assertTrialPathWroteNothing();
});

it('rejects a verification response that is merely truthy rather than true', function () {
    // Anything short of an explicit JSON true is a no.
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => 'true'])]);

    $this->from(route('home'))
        ->post(route('ifa.submit'), trialForm(['cf-turnstile-response' => 'x']))
        ->assertSessionHasErrors(TurnstileVerifier::FIELD);

    assertTrialPathWroteNothing();
});

// ---------------------------------------------------------------------------
// A real person still gets their trial
// ---------------------------------------------------------------------------

it('still creates the user, trial and intake for a verified submission', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->from(route('home'))
        ->post(route('ifa.submit'), trialForm([
            'email' => 'real.advisor@example.test',
            'cf-turnstile-response' => 'good-token',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding'));

    $user = User::where('email', 'real.advisor@example.test')->first();

    expect($user)->not->toBeNull()
        ->and(Subscription::where('user_id', $user->id)->where('status', 'trial')->exists())->toBeTrue()
        ->and(ClientIntake::where('email', 'real.advisor@example.test')->exists())->toBeTrue();

    Mail::assertQueued(AdminFreeTrialLeadSubmittedMail::class);
});

// ---------------------------------------------------------------------------
// The widget is on the form
// ---------------------------------------------------------------------------

it('renders the Turnstile widget with the configured site key on the home page', function () {
    config()->set('services.turnstile.site_key', 'site-key-for-test');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertSee('class="cf-turnstile" data-sitekey="site-key-for-test"', false);
});

// ---------------------------------------------------------------------------
// TurnstileVerifier itself
// ---------------------------------------------------------------------------

it('sends the secret, token and client IP to siteverify', function () {
    config()->set('services.turnstile.secret', 'secret-for-test');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $request = Request::create('/any', 'POST', [TurnstileVerifier::FIELD => 'tok-123'], server: ['REMOTE_ADDR' => '203.0.113.7']);

    (new TurnstileVerifier)->verify($request);

    Http::assertSent(fn (ClientRequest $sent) => $sent->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $sent['secret'] === 'secret-for-test'
        && $sent['response'] === 'tok-123'
        && $sent['remoteip'] === '203.0.113.7');
});

it('throws a validation error keyed on the token field when verification fails', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $request = Request::create('/any', 'POST', [TurnstileVerifier::FIELD => 'bad']);

    try {
        (new TurnstileVerifier)->verify($request);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey(TurnstileVerifier::FIELD);

        return;
    }

    $this->fail('Expected ValidationException.');
});
