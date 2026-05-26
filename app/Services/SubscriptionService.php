<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SubscriptionService
{
    public function activate(Payment $payment): User
    {
        $plan = Plan::find($payment->plan_id);
        $durationDays = $plan?->duration_days ?? 30;
        $startsAt = now();
        $endsAt = $startsAt->copy()->addDays($durationDays);

        $user = User::firstOrCreate(
            [
                'email' => $payment->email,
            ],
            [
                'name' => $payment->name,
                'password' => Hash::make(str()->random(32)),
            ]
        );

        $user->subscriptions()->updateOrCreate([
            'provider' => 'razorpay',
            'provider_subscription_id' => $payment->payment_id,
        ], [
            'plan_id' => $payment->plan_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'renewal_at' => $endsAt,
            'status' => 'active',
        ]);

        $payment->update([
            'user_id' => $user->id,
            'status' => Payment::STATUS_PAID,
            'processed_at' => now(),
        ]);

        return $user;
    }
}
