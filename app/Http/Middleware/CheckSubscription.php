<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Alias: 'paid'
 *
 * PERMISSIVE subscription gate — used on most protected routes.
 * Allows access if the user has ANY of:
 *   - An active subscription (status = active, ends_at in the future)
 *   - A trial subscription (status = trial, trial_ends_at in the future)
 *   - A grace period (subscription ended within the last 3 days)
 *
 * Use this on: /dashboard, /portfolio/*, /subscription, etc.
 *
 * @see EnsureActiveSubscription for the STRICT gate (active-only, no grace)
 */
class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        /*
        |--------------------------------------------------------------------------
        | GET MOST RELEVANT SUBSCRIPTION
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

        /*
        |--------------------------------------------------------------------------
        | NO SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        if (! $subscription) {
            return redirect()->route('pricing')
                ->with('error', 'Please choose a plan to continue.');
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIVE
        |--------------------------------------------------------------------------
        */

        if ($subscription->isActive()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | TRIAL
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
        | GRACE PERIOD (3 DAYS)
        |--------------------------------------------------------------------------
        */

        if ($subscription->ends_at && now()->diffInDays($subscription->ends_at, false) >= -3) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | EXPIRED
        |--------------------------------------------------------------------------
        */

        if ($subscription->isExpired()) {
            return redirect()->route('pricing')
                ->with('error', 'Your subscription has expired. Please renew.');
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK
        |--------------------------------------------------------------------------
        */

        return redirect()->route('pricing')
            ->with('error', 'Access restricted.');
    }
}
