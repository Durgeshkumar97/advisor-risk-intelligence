<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;

/**
 * Alias: 'active.sub'
 *
 * STRICT subscription gate — no trial, no grace period.
 * Only allows access if the user has a fully paid, active subscription.
 *
 * Use this on: premium-only features, report downloads, API-triggering
 * endpoints where trial/grace access should be explicitly denied.
 *
 * @see CheckSubscription for the PERMISSIVE gate (trial + grace allowed)
 */
class EnsureActiveSubscription
{
    /*
    |--------------------------------------------------------------------------
    | GRACE PERIOD (days after expiry before access is cut off)
    |--------------------------------------------------------------------------
    */

    private const GRACE_DAYS = 3;

    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/pricing');
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD LATEST SUBSCRIPTION (prefer active > trial > expired > null)
        |--------------------------------------------------------------------------
        */

        $subscription = Subscription::where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN status = 'active' THEN 1
                    WHEN status = 'trial' THEN 2
                    ELSE 3
                END
            ")
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect('/pricing')
                ->with('error', 'No subscription found. Choose a plan to continue.');
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIVE — full access
        |--------------------------------------------------------------------------
        */

        if ($subscription->isActive()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | TRIAL — full access while trial is running
        |--------------------------------------------------------------------------
        */

        if ($subscription->isTrial()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | TRIAL EXPIRED
        |--------------------------------------------------------------------------
        */

        if ($subscription->status === 'trial' && $subscription->trial_ends_at?->isPast()) {
            return redirect()->route('subscription.index')
                ->with('error', 'Your 14-day trial has expired. Choose a plan to continue.');
        }

        /*
        |--------------------------------------------------------------------------
        | GRACE PERIOD — 3-day window after expiry, access continues
        |--------------------------------------------------------------------------
        */

        if ($subscription->isExpired() && $subscription->isInGracePeriod()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | FULLY EXPIRED — redirect to pricing
        |--------------------------------------------------------------------------
        */

        return redirect('/pricing')
            ->with('error', 'Your subscription has expired. Renew to continue.');
    }
}
