<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * Subscription::payment() matched payments.order_id against
 * provider_subscription_id, but that column is populated with the Razorpay
 * PAYMENT id — two different identifiers — so the relation always resolved to
 * null. Dormant (nothing called it), but wrong.
 */
class SubscriptionPaymentRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_relation_resolves_for_a_subscription_created_by_the_real_activation_path(): void
    {
        $plan = minimalActivePlan();

        $payment = Payment::create([
            'plan_id' => $plan->id,
            'order_id' => 'order_abc123',
            'payment_id' => 'pay_xyz789',
            'name' => 'Test IFA',
            'email' => 'relation@example.test',
            'phone' => '9876543210',
            'amount' => '999.00',
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'status' => Payment::STATUS_PAID,
        ]);

        // Build the subscription the way production does, rather than
        // hand-crafting one that would trivially satisfy the assertion.
        app(SubscriptionService::class)->activate($payment);

        $subscription = Subscription::where('provider_subscription_id', 'pay_xyz789')->firstOrFail();

        $this->assertNotNull(
            $subscription->payment,
            'Subscription::payment() must resolve for a subscription created by SubscriptionService::activate().',
        );
        $this->assertSame($payment->id, $subscription->payment->id);
    }

    public function test_it_matches_on_payment_id_and_not_on_order_id(): void
    {
        $plan = minimalActivePlan();
        $user = User::factory()->create();

        // A decoy whose ORDER id equals the target's PAYMENT id. Under the old
        // mapping this is the row that would have been returned.
        Payment::create([
            'plan_id' => $plan->id,
            'order_id' => 'pay_target',
            'payment_id' => 'pay_decoy',
            'name' => 'Decoy',
            'email' => 'decoy@example.test',
            'phone' => '9876543210',
            'amount' => '1.00',
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'status' => Payment::STATUS_PAID,
        ]);

        $real = Payment::create([
            'plan_id' => $plan->id,
            'order_id' => 'order_real',
            'payment_id' => 'pay_target',
            'name' => 'Real',
            'email' => 'real@example.test',
            'phone' => '9876543210',
            'amount' => '999.00',
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'status' => Payment::STATUS_PAID,
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'renewal_at' => now()->addDays(30),
            'provider' => 'razorpay',
            'provider_subscription_id' => 'pay_target',
        ]);

        $this->assertSame($real->id, $subscription->payment->id);
    }
}
