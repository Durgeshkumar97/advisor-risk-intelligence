<?php

namespace App\Actions\Payments;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class CompletePaymentAction
{
    public function execute(Payment $payment): void
    {
        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE PROCESSING
        |--------------------------------------------------------------------------
        */

        if ($payment->processed_at) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | FIND OR CREATE USER
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'email',
            $payment->email
        )->first();

        if (!$user) {

            $temporaryPassword = Str::random(12);

            $user = User::create([

                'name' => $payment->name,

                'email' => $payment->email,

                'password' => Hash::make($temporaryPassword),
            ]);

            /*
            |--------------------------------------------------------------------------
            | TODO:
            | SEND EMAIL HERE
            |--------------------------------------------------------------------------
            |
            | Send:
            | - temporary password
            | - password setup link
            |
            */
        }

        /*
        |--------------------------------------------------------------------------
        | ATTACH USER TO PAYMENT
        |--------------------------------------------------------------------------
        */

        $payment->update([
            'user_id' => $user->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        app(CreateSubscriptionFromPaymentAction::class)
            ->execute($payment);

        /*
        |--------------------------------------------------------------------------
        | MARK PAYMENT PROCESSED
        |--------------------------------------------------------------------------
        */

        $payment->update([
            'processed_at' => now(),
        ]);
    }
}
