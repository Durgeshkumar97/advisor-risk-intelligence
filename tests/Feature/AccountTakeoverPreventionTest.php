<?php

namespace Tests\Feature;

use App\Actions\Payments\CompletePaymentAction;
use App\Models\ClientIntake;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Notifications\ExistingAccountLoginLinkNotification;
use App\Services\Notifications\WelcomeSetPasswordNotification;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the unauthenticated-email-match account-takeover fix:
 * an attacker who types a VICTIM's email into the trial-signup form or the
 * checkout form must never end up authenticated as that victim's existing
 * account. Only a genuinely NEW email may be auto-logged in.
 */
class AccountTakeoverPreventionTest extends TestCase
{
    use RefreshDatabase;

    private function starterPlan(): Plan
    {
        return Plan::create([
            'name'            => 'Starter',
            'slug'            => 'starter',
            'price'           => 0,
            'duration_days'   => 30,
            'portfolio_limit' => 1,
            'trial_days'      => 14,
            'is_active'       => true,
        ]);
    }

    // ─── Trial signup (IntakeController → StoreIfaTrialLeadAction) ──────────

    public function test_trial_signup_with_an_existing_users_email_does_not_log_in_as_that_user(): void
    {
        Notification::fake();
        $this->starterPlan();

        $victim = User::factory()->create(['email' => 'victim@example.test']);

        $response = $this->post(route('ifa.submit'), [
            'advisor_name' => 'Attacker',
            'whatsapp'     => '+91 99999 00000',
            'email'        => 'victim@example.test',
            'firm_name'    => 'Fake Firm',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        Notification::assertSentTo($victim, ExistingAccountLoginLinkNotification::class);

        // The lead is still recorded for admin follow-up — this isn't silently dropped.
        $this->assertDatabaseHas('client_intakes', [
            'email' => 'victim@example.test',
        ]);

        // The victim's own name/account are untouched by the attacker's submission.
        $this->assertSame('victim@example.test', $victim->fresh()->email);
    }

    public function test_trial_signup_with_a_genuinely_new_email_still_auto_logs_in(): void
    {
        Notification::fake();
        $this->starterPlan();

        $response = $this->post(route('ifa.submit'), [
            'advisor_name' => 'Brand New Advisor',
            'whatsapp'     => '+91 98765 43210',
            'email'        => 'new-advisor@example.test',
            'firm_name'    => 'New Advisor Firm',
        ]);

        $newUser = User::where('email', 'new-advisor@example.test')->first();

        $this->assertNotNull($newUser);
        $this->assertAuthenticatedAs($newUser);
        $response->assertRedirect(route('onboarding'));

        Notification::assertNotSentTo($newUser, ExistingAccountLoginLinkNotification::class);
    }

    // ─── SubscriptionService::activate() (webhook / fallback fulfilment) ────

    public function test_activating_a_payment_for_an_existing_email_sends_a_login_link_and_never_authenticates_anyone(): void
    {
        Notification::fake();
        $plan   = $this->starterPlan();
        $victim = User::factory()->create(['email' => 'victim2@example.test']);

        $payment = Payment::create([
            'plan_id'  => $plan->id,
            'order_id' => 'order_existing_' . uniqid(),
            'name'     => 'Whoever Paid',
            'email'    => 'victim2@example.test',
            'phone'    => '9876543210',
            'amount'   => '999.00',
            'currency' => 'INR',
            'gateway'  => 'razorpay',
            'status'   => Payment::STATUS_PENDING,
        ]);

        $user = app(SubscriptionService::class)->activate($payment);

        $this->assertTrue($user->is($victim));
        $this->assertGuest(); // SubscriptionService never authenticates anyone directly

        Notification::assertSentTo($victim, ExistingAccountLoginLinkNotification::class);
        Notification::assertNotSentTo($victim, WelcomeSetPasswordNotification::class);
    }

    public function test_activating_a_payment_for_a_new_email_sends_the_welcome_set_password_link(): void
    {
        Notification::fake();
        $plan = $this->starterPlan();

        $payment = Payment::create([
            'plan_id'  => $plan->id,
            'order_id' => 'order_new_' . uniqid(),
            'name'     => 'Brand New Customer',
            'email'    => 'brand-new@example.test',
            'phone'    => '9876543210',
            'amount'   => '999.00',
            'currency' => 'INR',
            'gateway'  => 'razorpay',
            'status'   => Payment::STATUS_PENDING,
        ]);

        $user = app(SubscriptionService::class)->activate($payment);

        $this->assertSame('brand-new@example.test', $user->email);

        Notification::assertSentTo($user, WelcomeSetPasswordNotification::class);
        Notification::assertNotSentTo($user, ExistingAccountLoginLinkNotification::class);
    }

    // ─── CompletePaymentAction (currently dormant — must be safe if wired in) ─

    public function test_complete_payment_action_does_not_login_when_email_matches_an_existing_user(): void
    {
        Notification::fake();
        $plan   = $this->starterPlan();
        $victim = User::factory()->create(['email' => 'victim3@example.test']);

        $payment = Payment::create([
            'plan_id'  => $plan->id,
            'order_id' => 'order_dormant_existing_' . uniqid(),
            'name'     => 'Whoever Paid',
            'email'    => 'victim3@example.test',
            'phone'    => '9876543210',
            'amount'   => '999.00',
            'currency' => 'INR',
            'gateway'  => 'razorpay',
            'status'   => Payment::STATUS_PAID,
        ]);

        app(CompletePaymentAction::class)->execute($payment);

        $this->assertGuest();
        Notification::assertSentTo($victim, ExistingAccountLoginLinkNotification::class);
    }

    public function test_complete_payment_action_logs_in_a_genuinely_new_user(): void
    {
        // CompletePaymentAction regenerates the session on login. That only
        // works inside a real HTTP request, where the StartSession
        // middleware binds a session onto the request — this action isn't
        // wired into any route today (dormant), so calling it directly
        // means the test has to bind one itself.
        $this->startSession();
        $this->app['request']->setLaravelSession($this->app['session']->driver());

        Notification::fake();
        $plan = $this->starterPlan();

        $payment = Payment::create([
            'plan_id'  => $plan->id,
            'order_id' => 'order_dormant_new_' . uniqid(),
            'name'     => 'Brand New Customer',
            'email'    => 'brand-new-dormant@example.test',
            'phone'    => '9876543210',
            'amount'   => '999.00',
            'currency' => 'INR',
            'gateway'  => 'razorpay',
            'status'   => Payment::STATUS_PAID,
        ]);

        app(CompletePaymentAction::class)->execute($payment);

        $newUser = User::where('email', 'brand-new-dormant@example.test')->first();

        $this->assertNotNull($newUser);
        $this->assertAuthenticatedAs($newUser);
    }

    // ─── Registration over a soft-deleted account's email ───────────────────

    public function test_registering_over_a_soft_deleted_email_does_not_change_its_password_or_login_the_attacker(): void
    {
        Notification::fake();

        $victim = User::factory()->create([
            'email'    => 'trashed-victim@example.test',
            'password' => 'original-password-123',
        ]);
        $victim->delete(); // soft delete

        $response = $this->post(route('register'), [
            'name'                  => 'Attacker',
            'email'                 => 'trashed-victim@example.test',
            'password'              => 'attacker-chosen-password',
            'password_confirmation' => 'attacker-chosen-password',
        ]);

        // Never auto-logged in as the restored account.
        $this->assertGuest();
        $response->assertRedirect(route('login'));

        // The account is restored (no longer soft-deleted)...
        $victim->refresh();
        $this->assertFalse($victim->trashed());

        // ...but its ORIGINAL password is untouched — gating Auth::login()
        // alone would NOT have caught a leftover password overwrite here.
        $this->assertTrue(Hash::check('original-password-123', $victim->password));
        $this->assertFalse(Hash::check('attacker-chosen-password', $victim->password));

        // The real owner is notified.
        Notification::assertSentTo($victim, ExistingAccountLoginLinkNotification::class);

        // The test that would have caught an incomplete fix (gating the login
        // call without also stripping the password): attempt to actually log
        // in with the attacker-chosen password via the real, password-verified
        // /login form. This must fail.
        $this->post(route('login'), [
            'email'    => 'trashed-victim@example.test',
            'password' => 'attacker-chosen-password',
        ]);
        $this->assertGuest();

        // The real owner's original password still works.
        $this->post(route('login'), [
            'email'    => 'trashed-victim@example.test',
            'password' => 'original-password-123',
        ]);
        $this->assertAuthenticatedAs($victim);
    }

    public function test_registering_with_a_genuinely_new_email_still_works_end_to_end(): void
    {
        $response = $this->post(route('register'), [
            'name'                  => 'Brand New User',
            'email'                 => 'brand-new-register@example.test',
            'password'              => 'a-secure-password-1',
            'password_confirmation' => 'a-secure-password-1',
        ]);

        $newUser = User::where('email', 'brand-new-register@example.test')->first();

        $this->assertNotNull($newUser);
        $this->assertAuthenticatedAs($newUser);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registering_with_an_active_existing_email_still_fails_validation(): void
    {
        User::factory()->create(['email' => 'already-active@example.test']);

        $response = $this->post(route('register'), [
            'name'                  => 'Someone Else',
            'email'                 => 'already-active@example.test',
            'password'              => 'a-secure-password-1',
            'password_confirmation' => 'a-secure-password-1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ─── Trial signup over a soft-deleted account (duplicate-lead branch) ───

    public function test_trial_signup_duplicate_of_a_soft_deleted_account_restores_it_and_notifies_the_owner(): void
    {
        Notification::fake();
        $this->starterPlan();

        $victim = User::factory()->create(['email' => 'trashed-trial@example.test']);
        $victim->delete();

        ClientIntake::create([
            'submission_uuid' => (string) Str::uuid(),
            'name'            => 'Original Advisor',
            'email'           => 'trashed-trial@example.test',
            'whatsapp'        => '+91 90000 00000',
            'firm_name'       => 'Original Firm',
            'status'          => 'trial',
        ]);

        $response = $this->post(route('ifa.submit'), [
            'advisor_name' => 'Attacker',
            'whatsapp'     => '+91 99999 99999',
            'email'        => 'trashed-trial@example.test',
            'firm_name'    => 'Fake Firm',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $victim->refresh();
        $this->assertFalse($victim->trashed());

        Notification::assertSentTo($victim, ExistingAccountLoginLinkNotification::class);
    }
}
