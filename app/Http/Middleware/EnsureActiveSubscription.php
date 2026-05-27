<?php

namespace App\Http\Middleware;

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
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->subscription || !$user->subscription->isActive()) {
            return redirect('/pricing');
        }

        return $next($request);
    }
}