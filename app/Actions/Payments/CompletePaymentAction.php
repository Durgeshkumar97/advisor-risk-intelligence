<?php

namespace App\Actions\Payments;

use App\Models\Payment;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        DB::transaction(function () use ($payment) {

            /*
            |--------------------------------------------------------------------------
            | REFRESH PAYMENT
            |--------------------------------------------------------------------------
            */

            $payment->refresh();

            /*
            |--------------------------------------------------------------------------
            | ENSURE PAYMENT IS PAID
            |--------------------------------------------------------------------------
            */

            if ($payment->status !== 'paid') {

                throw new \Exception(
                    'Cannot complete unpaid payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE / FIND USER
            |--------------------------------------------------------------------------
            */

            $user = app(
                CreateUserFromPaymentAction::class
            )->execute($payment);

            /*
            |--------------------------------------------------------------------------
            | CREATE SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            app(
                CreateSubscriptionFromPaymentAction::class
            )->execute($payment);

            /*
            |--------------------------------------------------------------------------
            | AUTO LOGIN USER
            |--------------------------------------------------------------------------
            */

            Auth::login($user);

            /*
            |--------------------------------------------------------------------------
            | REGENERATE SESSION
            |--------------------------------------------------------------------------
            */

            request()->session()->regenerate();

            /*
            |--------------------------------------------------------------------------
            | MARK PAYMENT PROCESSED
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'processed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | LOG SUCCESS
            |--------------------------------------------------------------------------
            */

            Log::info('Payment completed successfully', [

                'payment_id' => $payment->id,

                'user_id' => $user->id,

                'order_id' => $payment->order_id,
            ]);
        });
    }
}
