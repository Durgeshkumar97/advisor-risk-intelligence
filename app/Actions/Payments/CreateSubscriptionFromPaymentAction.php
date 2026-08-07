<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class CreateSubscriptionFromPaymentAction
{
    public function execute(Payment $payment): void
    {
        /*
        |--------------------------------------------------------------------------
        | LOAD PLAN
        |--------------------------------------------------------------------------
        */

        $plan = $payment->plan;

        if (! $plan) {

            Log::error(
                'CreateSubscriptionFromPaymentAction: plan not found.',
                ['plan_id' => $payment->plan_id]
            );

            throw new \RuntimeException(
                "Plan {$payment->plan_id} not found for payment {$payment->id}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE ACTIVE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        $exists = Subscription::query()
            ->where('user_id', $payment->user_id)
            ->where('plan_id', $payment->plan_id)
            ->where('status', 'active')
            ->exists();

        if ($exists) {

            Log::info(
                'CreateSubscriptionFromPaymentAction: subscription already active, skipping.',
                [
                    'user_id' => $payment->user_id,
                    'plan_id' => $payment->plan_id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        $durationDays = $plan->duration_days ?? 30;

        Subscription::create([

            'user_id' => $payment->user_id,

            'plan_id' => $payment->plan_id,

            'status' => 'active',

            'starts_at' => now(),

            'ends_at' => now()->addDays($durationDays),

            'renewal_at' => now()->addDays($durationDays),

            'provider' => 'razorpay',

            'provider_subscription_id' => $payment->payment_id,
        ]);

        Log::info(
            'CreateSubscriptionFromPaymentAction: subscription created.',
            [
                'user_id' => $payment->user_id,
                'plan_id' => $payment->plan_id,
                'duration_days' => $durationDays,
            ]
        );
    }
}
