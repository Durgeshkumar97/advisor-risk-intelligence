<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
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

        if (!$subscription) {
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