<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('admin.login');
        }

        if (auth()->user()->is_admin != 1) {
            auth()->logout();
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}