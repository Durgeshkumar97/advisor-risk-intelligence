<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateUserFromPaymentAction
{
    public function execute(Payment $payment): User
    {
        /*
        |----------------------------------------------------------------------
        | EXISTING USER — link payment and return early
        |----------------------------------------------------------------------
        */

        $existingUser = User::where('email', $payment->email)->first();

        if ($existingUser) {

            if (!$payment->user_id) {

                $payment->update(['user_id' => $existingUser->id]);
            }

            return $existingUser;
        }

        /*
        |----------------------------------------------------------------------
        | NEW USER — create with a random secure password
        |----------------------------------------------------------------------
        |
        | The user is auto-logged in immediately by CompletePaymentAction,
        | so the password is never shown. A magic-link welcome email is
        | dispatched (queued) so they can log back in later without needing
        | to set a password first.
        |
        */

        $loginToken = Str::random(60);

        $user = User::create([
            'name'        => $payment->name ?? 'Advisor',
            'email'       => $payment->email,
            'password'    => Hash::make(Str::random(32)),
            'login_token' => $loginToken,
        ]);

        /*
        |----------------------------------------------------------------------
        | LINK PAYMENT
        |----------------------------------------------------------------------
        */

        $payment->update(['user_id' => $user->id]);

        /*
        |----------------------------------------------------------------------
        | QUEUE WELCOME NOTIFICATION (magic link inside)
        |----------------------------------------------------------------------
        |
        | Uses the existing PaymentSuccessNotification which now embeds the
        | login_token so the user can access their dashboard with one click,
        | even if they close the browser before the auto-login redirect lands.
        |
        */

        $user->notify(new PaymentSuccessNotification($payment));

        Log::info('CreateUserFromPaymentAction: new user created from payment.', [
            'user_id'    => $user->id,
            'payment_id' => $payment->id,
        ]);

        return $user;
    }
}
