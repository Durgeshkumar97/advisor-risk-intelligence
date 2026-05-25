<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Portfolio;
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
        |
        | If the user already exists we do NOT create another default portfolio;
        | they already have their own portfolios from before.
        |
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
        | NEW USER — create with a random secure password + magic-link token
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
        | DEFAULT PORTFOLIO (Bug #8 fix)
        |----------------------------------------------------------------------
        |
        | Every new user needs at least one portfolio so that:
        |  1. PortfolioUploadController has something to attach files to.
        |  2. ProcessPortfolioFile gets a valid portfolio_id — without it,
        |     PortfolioAssets are created with portfolio_id = null (orphans).
        |
        | The portfolio name uses the user's name so it feels personalised
        | right out of the box. They can rename or add more from the dashboard.
        |
        */

        Portfolio::create([
            'user_id'    => $user->id,
            'name'       => 'My Portfolio',
            'total_value' => 0,
            'risk_score' => 0,
            'risk_level' => 'LOW',
        ]);

        Log::info('CreateUserFromPaymentAction: default portfolio created.', [
            'user_id' => $user->id,
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
        | PaymentSuccessNotification embeds the login_token so the user can
        | access their dashboard with one click, even after the browser closes.
        | Also dispatches the WhatsApp welcome job if the user has a phone.
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
