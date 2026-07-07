<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Notifications\WelcomeSetPasswordNotification;
use App\Services\UserAccountRecoveryService;

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

        if (!$plan) {
            Log::error('SubscriptionService: plan not found.', [
                'plan_id'    => $payment->plan_id,
                'payment_id' => $payment->id,
            ]);
            throw new \RuntimeException("Plan {$payment->plan_id} not found.");
        }

        $user = $this->findOrCreateUser($payment);

        if ($user->wasRecentlyCreated) {
            $resetToken     = Password::broker()->createToken($user);
            $setPasswordUrl = route('password.reset', ['token' => $resetToken])
                              . '?email=' . urlencode($user->email);
            $user->notify(new WelcomeSetPasswordNotification($setPasswordUrl));
        } else {
            // This payment's email matched an EXISTING account. The payer is
            // never auto-logged in from this unauthenticated flow — send a
            // login link to the account's own address instead, so its real
            // owner has a safe way back in regardless of who paid.
            $this->accounts->sendLoginLinkToExistingUser($user);
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
        $endsAt   = $startsAt->copy()->addDays($durationDays);

        DB::transaction(function () use ($user, $payment, $plan, $startsAt, $endsAt) {

            $user->subscriptions()->updateOrCreate(
                [
                    'provider'                  => 'razorpay',
                    'provider_subscription_id'  => $payment->payment_id,
                ],
                [
                    'plan_id'    => $plan->id,
                    'starts_at'  => $startsAt,
                    'ends_at'    => $endsAt,
                    'renewal_at' => $endsAt,
                    'status'     => 'active',
                ]
            );

            $payment->update([
                'user_id'      => $user->id,
                'status'       => Payment::STATUS_PAID,
                'processed_at' => now(),
            ]);

        });

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
        $result = $this->accounts->findRestoreOrCreateUserByEmail(
            $payment->email,
            [
                'name'     => $payment->name ?? 'Advisor',
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
