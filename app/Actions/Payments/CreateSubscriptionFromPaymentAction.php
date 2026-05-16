<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Subscription;

class CreateSubscriptionFromPaymentAction
{
    public function execute(Payment $payment): void
    {
        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE SUBSCRIPTIONS
        |--------------------------------------------------------------------------
        */

        $exists = Subscription::where(
            'user_id',
            $payment->user_id
        )
            ->where(
                'plan_id',
                $payment->plan_id
            )
            ->where(
                'status',
                'active'
            )
            ->exists();

        if ($exists) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        Subscription::create([

            'user_id' => $payment->user_id,

            'plan_id' => $payment->plan_id,

            'status' => 'active',

            'starts_at' => now(),

            'ends_at' => now()->addDays(30),

            'renewal_at' => now()->addDays(30),

            'provider' => 'razorpay',
        ]);
    }
}
