<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    /**
     * Session key holding the unix timestamp of the last /admin request.
     */
    private const LAST_ACTIVITY_KEY = 'admin_last_activity';

    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->route('admin.login');
        }

        if (auth()->user()->is_admin != 1) {
            auth()->logout();
            abort(403, 'Unauthorized.');
        }

        /*
        |----------------------------------------------------------------------
        | ADMIN IDLE TIMEOUT
        |----------------------------------------------------------------------
        |
        | The short admin window used to be enforced by pinning
        | session.lifetime to 15 globally, which timed out paying advisors on
        | the same clock. It is enforced here instead so it applies to /admin
        | alone.
        |
        | Checked explicitly rather than by overriding session.lifetime per
        | request: the session handler binds its expiry when StartSession
        | constructs it, before this middleware runs, and expire_on_close
        | already makes the cookie a browser-session cookie — so a runtime
        | config override would have no effect at all.
        |
        */

        $timeout = (int) config('session.admin_lifetime', 15);
        $lastActivity = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, 0);

        if ($timeout > 0 && $lastActivity > 0 && (time() - $lastActivity) > $timeout * 60) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with(
                'error',
                "Your admin session expired after {$timeout} minutes of inactivity. Please sign in again."
            );
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, time());

        return $next($request);
    }
}
