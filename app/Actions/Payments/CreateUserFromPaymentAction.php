<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Portfolio;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\WelcomeSetPasswordNotification;
use App\Services\UserAccountRecoveryService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUserFromPaymentAction
{
    public function __construct(
        private readonly UserAccountRecoveryService $accounts,
    ) {}

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

        $loginToken = Str::random(60);

        $result = $this->accounts->findRestoreOrCreateUserByEmail(
            $payment->email,
            [
                'name'                   => $payment->name ?? 'Advisor',
                'password'               => Hash::make(Str::random(32)),
                'login_token'            => $loginToken,
                'login_token_expires_at' => now()->addHours(24),
            ],
            [
                'name' => $payment->name ?? 'Advisor',
            ],
        );

        $user = $result['user'];
        $user->wasRecentlyCreated = $result['created'];

        if (!$payment->user_id) {
            $payment->update(['user_id' => $user->id]);
        }

        if (! $result['created']) {
            return $user;
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
        | QUEUE NOTIFICATIONS
        |----------------------------------------------------------------------
        |
        | 1. PaymentSuccessNotification — payment receipt with plan/amount.
        | 2. WelcomeSetPasswordNotification — set-password link so the user
        |    can log back in after the current browser session ends.
        |    Uses Laravel's password-reset broker (password_reset_tokens).
        |
        */

        $user->notify(new PaymentSuccessNotification($payment));

        $resetToken     = Password::broker()->createToken($user);
        $setPasswordUrl = route('password.reset', ['token' => $resetToken])
                          . '?email=' . urlencode($user->email);

        $user->notify(new WelcomeSetPasswordNotification($setPasswordUrl));

        Log::info('CreateUserFromPaymentAction: new user created from payment.', [
            'user_id'    => $user->id,
            'payment_id' => $payment->id,
        ]);

        return $user;
    }
}
