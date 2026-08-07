<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if (! $user) {
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

        if (! $subscription) {
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
        | ANYTHING ELSE (trial, grace period, expired) — no access, no exceptions
        |--------------------------------------------------------------------------
        */

        return redirect('/pricing')
            ->with('error', 'This action requires an active subscription.');
    }
}
