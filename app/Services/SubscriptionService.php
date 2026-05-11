<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SubscriptionService
{
    public function activate(Payment $payment): User
    {
        $user = User::firstOrCreate(
            [
                'email' => $payment->email,
            ],
            [
                'name' => $payment->name,
                'password' => Hash::make(str()->random(32)),
            ]
        );

        $user->subscriptions()->create([
            'plan_id' => $payment->plan_id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $payment->update([
            'user_id' => $user->id,
            'processed_at' => now(),
        ]);

        return $user;
    }
}
