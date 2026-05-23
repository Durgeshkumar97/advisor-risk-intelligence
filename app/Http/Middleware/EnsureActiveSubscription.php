<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;

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
            ->orderByRaw("FIELD(status, 'active', 'trial', 'expired') ASC")
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
