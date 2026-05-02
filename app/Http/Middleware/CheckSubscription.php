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
            return redirect('/login');
        }

        $sub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();

        if (!$sub) {
            return redirect('/pricing');
        }

        return $next($request);
    }
}