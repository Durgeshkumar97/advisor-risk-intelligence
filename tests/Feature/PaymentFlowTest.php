<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\VerifyRazorpayPayment;
use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(\Tests\TestCase::class, RefreshDatabase::class);

// ─── file-level helpers ───────────────────────────────────────────────────────

/**
 * Build a Razorpay webhook JSON body and its matching HMAC-SHA256 signature.
 * Using call() rather than postJson() later so the raw body string and the
 * computed HMAC are identical — postJson() re-encodes and would break the check.
 */
function razorpayWebhookBody(
    string $orderId,
    string $paymentId,
    string $secret,
    string $event = 'payment.captured',
): array {
    $body = json_encode([
        'event'   => $event,
        'payload' => [
            'payment' => [
                'entity' => ['id' => $paymentId, 'order_id' => $orderId],
            ],
        ],
    ]);

    return [
        'body' => $body,
        'sig'  => hash_hmac('sha256', $body, $secret),
    ];
}

/** POST a raw body to /webhook/razorpay with the given X-Razorpay-Signature header. */
function postWebhook(string $body, string $sig): \Illuminate\Testing\TestResponse
{
    return test()->call(
        'POST',
        route('webhook.razorpay'),
        [],   // params
        [],   // cookies
        [],   // files
        ['HTTP_X-RAZORPAY-SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body,
    );
}

/** Minimal Plan row — only the fields the migration requires non-null. */
function testPlan(): Plan
{
    return Plan::create([
        'name'          => 'Starter',
        'slug'          => 'starter',
        'price'         => '999.00',
        'duration_days' => 30,
        'is_active'     => true,
    ]);
}

/** Pending Payment tied to $plan, optionally owned by $user. */
function pendingPayment(Plan $plan, ?User $user = null, string $orderId = ''): Payment
{
    return Payment::create([
        'plan_id'  => $plan->id,
        'user_id'  => $user?->id,
        'order_id' => $orderId ?: 'order_' . uniqid(),
        'name'     => 'Test IFA',
        'email'    => 'ifa@example.com',
        'phone'    => '9876543210',
        'amount'   => '999.00',
        'currency' => 'INR',
        'gateway'  => 'razorpay',
        'status'   => Payment::STATUS_PENDING,
    ]);
}

// ─── CheckoutController::success()  (POST /payment/verify) ───────────────────

describe('CheckoutController::success()', function () {

    beforeEach(function () {
        Queue::fake();
        $this->plan = testPlan();
    });

    it('marks payment as processing and dispatches job when signature is valid', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_happy_001');

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_happy_001',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_happy_001',
        ])
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'redirect']);

        // VerifyRazorpayPayment sets status=paid; CheckoutController then sets
        // status=processing before dispatching the fulfilment job.
        expect($payment->fresh()->status)->toBe(Payment::STATUS_PROCESSING);
        Queue::assertPushedTimes(ProcessSuccessfulPayment::class, 1);
    });

    it('returns 422 and leaves payment untouched when the signature is invalid', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_bad_sig');

        // RazorpayService rejects the signature — VerifyRazorpayPayment throws a
        // ValidationException before opening any DB transaction (sig-first ordering).
        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(false);

        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_tampered',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'forged_sig',
        ])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);

        expect($payment->fresh()->status)->toBe(Payment::STATUS_PENDING);
        expect($payment->fresh()->payment_id)->toBeNull();
        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    });

    it('returns 422 when required payload fields are missing', function () {
        // Missing order_id
        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_abc',
            'razorpay_signature'  => 'sig_abc',
        ])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);

        // Empty body
        $this->postJson(route('payment.verify'), [])
             ->assertUnprocessable()
             ->assertJson(['success' => false]);
    });

    it('never auto-logs in based on the payment\'s resolved user, and redirects to login', function () {
        // Simulates the fulfilment job (ProcessSuccessfulPayment, faked here via
        // Queue::fake()) having already matched this payment's email to an
        // EXISTING account by the time this check runs. The browser completing
        // checkout must never be logged in as that account — it could be
        // anyone who typed in that email, not its real owner. See
        // CheckoutController::success() and SubscriptionService::activate().
        $user    = User::factory()->create(['onboarding_completed' => true]);
        $payment = pendingPayment($this->plan, $user, 'order_existing_user');

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_user_001',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_user_001',
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'redirect' => route('login')]);

        $this->assertGuest();
    });

    it('redirects an already-authenticated buyer straight to their dashboard', function () {
        // Distinct from the case above: here the browser already had a real,
        // independently-established session BEFORE checkout even started —
        // Auth::user() reflects that, not an email match from the async job.
        $user = User::factory()->create(['onboarding_completed' => true]);
        $this->actingAs($user);

        $payment = pendingPayment($this->plan, orderId: 'order_already_logged_in');

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_loggedin_001',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_loggedin_001',
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'redirect' => route('dashboard')]);

        $this->assertAuthenticatedAs($user);
    });

    it('does not dispatch a second job when payment has already been processed', function () {
        // Simulates a client retry after the first call already completed:
        // processed_at is set (job ran), so CheckoutController's guard fires.
        $payment = Payment::create([
            'plan_id'      => $this->plan->id,
            'order_id'     => 'order_already_done',
            'name'         => 'Test IFA',
            'email'        => 'ifa@example.com',
            'phone'        => '9876543210',
            'amount'       => '999.00',
            'currency'     => 'INR',
            'gateway'      => 'razorpay',
            'status'       => Payment::STATUS_PAID,
            'processed_at' => now(),
        ]);

        // VerifyRazorpayPayment: sees status=paid → returns early without update.
        // CheckoutController: sees processed_at set → skips dispatch.
        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        $this->postJson(route('payment.verify'), [
            'razorpay_payment_id' => 'pay_dup',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_dup',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    });
});

// ─── WebhookController::handle()  (POST /webhook/razorpay) ───────────────────

describe('WebhookController::handle()', function () {

    beforeEach(function () {
        Queue::fake();
        $this->secret = 'test-wh-secret';
        config(['services.razorpay.webhook_secret' => $this->secret]);
        $this->plan = testPlan();
    });

    it('marks payment as processing and dispatches job for payment.captured', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_wh_ok');

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            $payment->order_id, 'pay_wh_111', $this->secret,
        );

        postWebhook($body, $sig)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        expect($payment->fresh()->status)->toBe(Payment::STATUS_PROCESSING);
        expect($payment->fresh()->payment_id)->toBe('pay_wh_111');
        Queue::assertPushedTimes(ProcessSuccessfulPayment::class, 1);
    });

    it('returns 400 and does not touch the database when the signature is invalid', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_wh_badsig');

        ['body' => $body] = razorpayWebhookBody(
            $payment->order_id, 'pay_wh_tampered', $this->secret,
        );

        // Valid body, forged signature — simulates a spoofed webhook.
        postWebhook($body, 'not-the-real-hmac')
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid signature']);

        // Signature check must happen before any DB interaction.
        expect($payment->fresh()->status)->toBe(Payment::STATUS_PENDING);
        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    });

    it('returns 200 ignored for events that are not payment.captured', function () {
        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            'order_irr', 'pay_irr', $this->secret, 'payment.failed',
        );

        postWebhook($body, $sig)
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    });

    it('is idempotent — same webhook delivered twice dispatches the job exactly once', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_wh_idem');

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            $payment->order_id, 'pay_wh_222', $this->secret,
        );

        // First delivery: pending → processing, one dispatch.
        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);
        Queue::assertPushedTimes(ProcessSuccessfulPayment::class, 1);

        // Second delivery of the identical payload (Razorpay retries on 5xx).
        // Controller sees status=processing → idempotency guard fires → no second dispatch.
        postWebhook($body, $sig)->assertOk();

        // Total dispatches must still be 1, not 2.
        Queue::assertPushedTimes(ProcessSuccessfulPayment::class, 1);
    });

    it('is idempotent when processed_at is set (job already completed)', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_wh_done');
        $payment->update([
            'status'       => Payment::STATUS_PROCESSING,
            'processed_at' => now(),
        ]);

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            $payment->order_id, 'pay_wh_333', $this->secret,
        );

        postWebhook($body, $sig)->assertOk();

        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    });

    it('returns 400 when the payment entity is absent from the payload', function () {
        $body = json_encode(['event' => 'payment.captured', 'payload' => []]);
        $sig  = hash_hmac('sha256', $body, $this->secret);

        postWebhook($body, $sig)
            ->assertStatus(400)
            ->assertJson(['error' => 'missing entity']);
    });

    it('returns 400 for a malformed JSON body even with a valid signature', function () {
        $body = 'not-valid-json';
        $sig  = hash_hmac('sha256', $body, $this->secret);

        postWebhook($body, $sig)->assertStatus(400);
    });

    it('revokes access via CheckSubscription when a refund webhook arrives — not just a DB status flip', function () {
        $user = User::factory()->create();

        $payment = Payment::create([
            'plan_id'      => $this->plan->id,
            'user_id'      => $user->id,
            'order_id'     => 'order_refund_gate',
            'payment_id'   => 'pay_refund_gate',
            'name'         => 'Test IFA',
            'email'        => 'ifa@example.com',
            'phone'        => '9876543210',
            'amount'       => '999.00',
            'currency'     => 'INR',
            'gateway'      => 'razorpay',
            'status'       => Payment::STATUS_PAID,
            'processed_at' => now(),
        ]);

        $subscription = Subscription::create([
            'user_id'                  => $user->id,
            'plan_id'                  => $this->plan->id,
            'status'                   => 'active',
            'starts_at'                => now(),
            'ends_at'                  => now()->addDays(20),
            'renewal_at'               => now()->addDays(20),
            'provider'                 => 'razorpay',
            'provider_subscription_id' => 'pay_refund_gate',
        ]);

        // Sanity check: access is genuinely granted BEFORE the refund.
        $this->actingAs($user)->get(route('portfolio.manage'))->assertOk();

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            'order_refund_gate', 'pay_refund_gate', $this->secret, 'payment.refunded',
        );

        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);

        expect($payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED);

        // The assertion that catches an incomplete fix (status-only, no
        // backdated ends_at): CheckSubscription's grace-period check reads
        // ends_at directly and never looks at status at all.
        expect($subscription->fresh()->ends_at->isPast())->toBeTrue();
        expect($subscription->fresh()->status)->toBe('cancelled');

        // The real proof: hit the actual gated route as this user and confirm
        // the middleware itself now denies access — not just a DB assertion.
        $this->actingAs($user)->get(route('portfolio.manage'))->assertRedirect();
    });

    it('is idempotent when the same refund webhook is delivered twice', function () {
        $user = User::factory()->create();

        Payment::create([
            'plan_id'      => $this->plan->id,
            'user_id'      => $user->id,
            'order_id'     => 'order_refund_idem',
            'payment_id'   => 'pay_refund_idem',
            'name'         => 'Test IFA',
            'email'        => 'ifa@example.com',
            'phone'        => '9876543210',
            'amount'       => '999.00',
            'currency'     => 'INR',
            'gateway'      => 'razorpay',
            'status'       => Payment::STATUS_PAID,
            'processed_at' => now(),
        ]);

        Subscription::create([
            'user_id'                  => $user->id,
            'plan_id'                  => $this->plan->id,
            'status'                   => 'active',
            'starts_at'                => now(),
            'ends_at'                  => now()->addDays(20),
            'renewal_at'               => now()->addDays(20),
            'provider'                 => 'razorpay',
            'provider_subscription_id' => 'pay_refund_idem',
        ]);

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            'order_refund_idem', 'pay_refund_idem', $this->secret, 'payment.refunded',
        );

        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);
        $firstEndsAt = Subscription::where('provider_subscription_id', 'pay_refund_idem')->first()->ends_at;

        // Second delivery of the identical payload — must not error or re-process.
        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);
        $secondEndsAt = Subscription::where('provider_subscription_id', 'pay_refund_idem')->first()->ends_at;

        expect($secondEndsAt->equalTo($firstEndsAt))->toBeTrue();
    });

    it('returns 200 without crashing when a refund webhook references an unknown payment', function () {
        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            'order_does_not_exist', 'pay_does_not_exist', $this->secret, 'payment.refunded',
        );

        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);
    });

    it('leaves payment.captured handling completely unaffected by the new refund branch', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_wh_unaffected');

        ['body' => $body, 'sig' => $sig] = razorpayWebhookBody(
            $payment->order_id, 'pay_wh_unaffected', $this->secret, 'payment.captured',
        );

        postWebhook($body, $sig)->assertOk()->assertJson(['status' => 'ok']);

        expect($payment->fresh()->status)->toBe(Payment::STATUS_PROCESSING);
        Queue::assertPushedTimes(ProcessSuccessfulPayment::class, 1);
    });
});

// ─── VerifyRazorpayPayment action ─────────────────────────────────────────────

describe('VerifyRazorpayPayment', function () {

    beforeEach(function () {
        $this->plan   = testPlan();
        $this->action = app(VerifyRazorpayPayment::class);
    });

    it('throws ValidationException before any DB write when the signature is invalid', function () {
        // Guards the signature-first ordering introduced in commit 34d4cf9.
        // Old bug: DB::transaction (with lockForUpdate) opened first, then sig checked.
        // Fixed:   sig rejected → exception → no transaction, no row lock, no mutation.
        $payment = pendingPayment($this->plan, orderId: 'order_sig_first');

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(false);

        expect(fn () => $this->action->execute([
            'razorpay_payment_id' => 'pay_bad',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'forged',
        ]))->toThrow(ValidationException::class);

        // If the old ordering were still in place the DB write could mutate these.
        expect($payment->fresh()->status)->toBe(Payment::STATUS_PENDING);
        expect($payment->fresh()->payment_id)->toBeNull();
        expect($payment->fresh()->paid_at)->toBeNull();
    });

    it('sets payment status to paid and records payment_id on valid signature', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_valid_sig');

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        $result = $this->action->execute([
            'razorpay_payment_id' => 'pay_real_001',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_real_001',
        ]);

        expect($result->status)->toBe(Payment::STATUS_PAID)
            ->and($result->payment_id)->toBe('pay_real_001')
            ->and($result->paid_at)->not->toBeNull();

        expect($payment->fresh()->status)->toBe(Payment::STATUS_PAID);
    });

    it('is idempotent when payment is already paid — returns without overwriting', function () {
        $payment = pendingPayment($this->plan, orderId: 'order_already_paid');
        $payment->update([
            'status'     => Payment::STATUS_PAID,
            'payment_id' => 'pay_original_001',
        ]);

        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        // Replay with a different payment_id — original must be preserved.
        $result = $this->action->execute([
            'razorpay_payment_id' => 'pay_duplicate_attempt',
            'razorpay_order_id'   => $payment->order_id,
            'razorpay_signature'  => 'sig_any',
        ]);

        expect($result->status)->toBe(Payment::STATUS_PAID)
            ->and($payment->fresh()->payment_id)->toBe('pay_original_001');
    });

    it('throws ValidationException when the order_id does not exist in the database', function () {
        $this->mock(RazorpayService::class)
            ->shouldReceive('verifySignature')->once()->andReturn(true);

        expect(fn () => $this->action->execute([
            'razorpay_payment_id' => 'pay_ghost',
            'razorpay_order_id'   => 'order_does_not_exist',
            'razorpay_signature'  => 'sig_ghost',
        ]))->toThrow(ValidationException::class);
    });
});
