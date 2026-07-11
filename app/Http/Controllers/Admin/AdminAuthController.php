<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW LOGIN
    |--------------------------------------------------------------------------
    */

    public function showLogin(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    |
    | Uses the standard web guard. After credentials pass, we verify is_admin.
    | No custom guard needed — we own the is_admin column.
    |
    */

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withErrors(['email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])])
                ->withInput($request->only('email'));
        }

        // Admin sessions never persist beyond the 15-minute session lifetime
        // (config/session.php) — no "remember me" cookie is ever queued here,
        // regardless of what the request sends.
        if (!Auth::attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            false
        )) {
            RateLimiter::hit($throttleKey);

            AdminLog::record(
                'admin_login_failed',
                targetUserId: User::where('email', $validated['email'])->value('id'),
            );

            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->withInput($request->only('email'));
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY ADMIN STATUS
        |--------------------------------------------------------------------------
        */

        if (!Auth::user()->is_admin) {
            $deniedUserId = Auth::id();

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey);

            AdminLog::record('admin_login_denied', targetUserId: $deniedUserId);

            return back()
                ->withErrors(['email' => 'Access denied. Admin account required.'])
                ->withInput($request->only('email'));
        }

        /*
        |--------------------------------------------------------------------------
        | REGENERATE SESSION
        |--------------------------------------------------------------------------
        */

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | RECORD LAST LOGIN
        |--------------------------------------------------------------------------
        */

        Auth::user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        AdminLog::record('admin_login_success', userId: Auth::id());

        return redirect()->route('admin.dashboard');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
