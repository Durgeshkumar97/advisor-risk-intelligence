<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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