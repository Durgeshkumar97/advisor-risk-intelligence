<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\UserAccountRecoveryService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompletePaymentAction
{
    public function execute(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            /*
            |--------------------------------------------------------------------------
            | LOCK ROW + PREVENT DOUBLE PROCESSING
            | lockForUpdate blocks a concurrent request here until this transaction
            | commits — the second caller then sees processed_at already set and exits.
            |--------------------------------------------------------------------------
            */

            $locked = Payment::lockForUpdate()->find($payment->id);

            if ($locked->processed_at) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | ENSURE PAYMENT IS PAID
            |--------------------------------------------------------------------------
            */

            if ($locked->status !== 'paid') {

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
            )->execute($locked);

            /*
            |--------------------------------------------------------------------------
            | CREATE SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            app(
                CreateSubscriptionFromPaymentAction::class
            )->execute($locked);

            /*
            |--------------------------------------------------------------------------
            | AUTO LOGIN — ONLY FOR A GENUINELY NEW ACCOUNT
            |--------------------------------------------------------------------------
            |
            | $user may be an EXISTING account matched purely by the email typed
            | into this unauthenticated payment form — never auto-login in that
            | case, since the payer could be anyone who knows that email, not
            | its owner. Only a freshly-created account is safe to log straight
            | into. The existing-account case gets a login-link email instead.
            |--------------------------------------------------------------------------
            */

            if ($user->wasRecentlyCreated) {
                Auth::login($user);
                request()->session()->regenerate();
            } else {
                app(UserAccountRecoveryService::class)->sendLoginLinkToExistingUser($user);
            }

            /*
            |--------------------------------------------------------------------------
            | MARK PAYMENT PROCESSED
            |--------------------------------------------------------------------------
            */

            $locked->update([
                'processed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | LOG SUCCESS
            |--------------------------------------------------------------------------
            */

            Log::info('Payment completed successfully', [

                'payment_id' => $locked->id,

                'user_id' => $user->id,

                'order_id' => $locked->order_id,
            ]);
        });
    }
}
