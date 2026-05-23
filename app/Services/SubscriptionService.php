<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /*
    |--------------------------------------------------------------------------
    | ACTIVATE
    |--------------------------------------------------------------------------
    |
    | Called by ProcessSuccessfulPayment job (webhook / fallback path).
    | Creates the user if not yet exists, then creates the subscription.
    | Idempotency is guarded by payment->processed_at.
    |
    */

    public function activate(Payment $payment): User
    {
        /*
        |----------------------------------------------------------------------
        | IDEMPOTENCY GUARD
        |----------------------------------------------------------------------
        */

        $payment->refresh();

        if ($payment->processed_at) {

            Log::info('SubscriptionService: payment already processed, skipping.', [
                'payment_id' => $payment->id,
            ]);

            return $payment->user ?? $this->findOrCreateUser($payment);
        }

        /*
        |----------------------------------------------------------------------
        | USER
        |----------------------------------------------------------------------
        */

        $user = $this->findOrCreateUser($payment);

        /*
        |----------------------------------------------------------------------
        | PLAN
        |----------------------------------------------------------------------
        */

        $plan = Plan::find($payment->plan_id);

        if (!$plan) {

            Log::error('SubscriptionService: plan not found.', [
                'plan_id'    => $payment->plan_id,
                'payment_id' => $payment->id,
            ]);

            throw new \RuntimeException(
                "Plan {$payment->plan_id} not found."
            );
        }

        /*
        |----------------------------------------------------------------------
        | SUBSCRIPTION (idempotent — skip if already active on same plan)
        |----------------------------------------------------------------------
        */

        $alreadyActive = Subscription::query()
            ->where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->exists();

        if (!$alreadyActive) {

            $durationDays = $plan->duration_days ?? 30;

            Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'status'     => 'active',
                'starts_at'  => now(),
                'ends_at'    => now()->addDays($durationDays),
                'renewal_at' => now()->addDays($durationDays),
                'provider'   => 'razorpay',
            ]);
        }

        /*
        |----------------------------------------------------------------------
        | MARK PAYMENT PROCESSED
        |----------------------------------------------------------------------
        */

        $payment->update([
            'user_id'      => $user->id,
            'processed_at' => now(),
        ]);

        Log::info('SubscriptionService: payment activated.', [
            'payment_id' => $payment->id,
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
        ]);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND OR CREATE USER
    |--------------------------------------------------------------------------
    */

    private function findOrCreateUser(Payment $payment): User
    {
        return User::firstOrCreate(
            ['email' => $payment->email],
            [
                'name'     => $payment->name ?? 'Advisor',
                'password' => Hash::make(str()->random(32)),
            ]
        );
    }
}
