<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Notifications\WelcomeSetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class SubscriptionService
{
    public function __construct(
        private readonly UserAccountRecoveryService $accounts,
    ) {}

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
        $plan = Plan::find($payment->plan_id);

        if (! $plan) {
            Log::error('SubscriptionService: plan not found.', [
                'plan_id' => $payment->plan_id,
                'payment_id' => $payment->id,
            ]);
            throw new \RuntimeException("Plan {$payment->plan_id} not found.");
        }

        $user = $this->findOrCreateUser($payment);

        if ($user->wasRecentlyCreated) {
            $resetToken = Password::broker()->createToken($user);
            $setPasswordUrl = route('password.reset', ['token' => $resetToken])
                              .'?email='.urlencode($user->email);
            $user->notify(new WelcomeSetPasswordNotification($setPasswordUrl));
        } else {
            /*
            | This payment's email matched an EXISTING account, from a flow
            | where nobody proved they own it. The payer is never authenticated
            | here — that is the security property, and it is unchanged.
            |
            | We used to also mint a 15-minute login token and email it to the
            | account owner as a convenience. That handed any anonymous payer a
            | way to make a real, valid login link land in a stranger's inbox on
            | demand: not account takeover (the token only ever goes to the
            | owner's own address), but a phishing-adjacent primitive with no
            | legitimate use — an existing user who pays while logged out
            | already knows their password or can use Forgot Password, and
            | still receives PaymentSuccessNotification either way.
            |
            | The subscription IS still extended. Refusing to fulfil an
            | unauthenticated payment would strand the common legitimate case —
            | an existing customer renewing from a different browser or device —
            | with no self-service way out.
            */
            Log::warning('SubscriptionService: payment email matched an existing account from an unauthenticated flow — subscription extended, no login link issued.', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);
        }

        $durationDays = $plan->duration_days ?? 30;

        // If the user has an active subscription with time remaining, stack the new
        // period from its end date so mid-period renewals don't erase paid days.
        $currentSub = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        $startsAt = $currentSub ? $currentSub->ends_at->max(now()) : now();
        $endsAt = $startsAt->copy()->addDays($durationDays);

        DB::transaction(function () use ($user, $payment, $plan, $startsAt, $endsAt) {

            $user->subscriptions()->updateOrCreate(
                [
                    'provider' => 'razorpay',
                    'provider_subscription_id' => $payment->payment_id,
                ],
                [
                    'plan_id' => $plan->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'renewal_at' => $endsAt,
                    'status' => 'active',
                ]
            );

            $payment->update([
                'user_id' => $user->id,
                'status' => Payment::STATUS_PAID,
                'processed_at' => now(),
            ]);

        });

        Log::info('SubscriptionService: payment activated.', [
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
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
        $result = $this->accounts->findRestoreOrCreateUserByEmail(
            $payment->email,
            [
                'name' => $payment->name ?? 'Advisor',
                'password' => Hash::make(str()->random(32)),
            ],
            [
                'name' => $payment->name ?? 'Advisor',
            ],
        );

        $user = $result['user'];
        $user->wasRecentlyCreated = $result['created'];

        return $user;
    }
}
