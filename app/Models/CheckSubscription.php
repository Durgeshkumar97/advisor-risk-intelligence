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

        $subscription = Subscription::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect()->route('pricing')
                ->with('error', 'Please choose a plan.');
        }

        if ($subscription->isActive() || $subscription->isTrial()) {
            return $next($request);
        }

        return redirect()->route('pricing')
            ->with('error', 'Subscription expired.');
    }
}