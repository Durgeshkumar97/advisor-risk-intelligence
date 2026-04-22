<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    /**
     * Show admin login page.
     */
    public function showLogin()
    {
        return view('admin.login');
    }

    /**
     * Handle admin login.
     */
    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Rate Limit Protection
        |--------------------------------------------------------------------------
        */

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {

            $seconds = RateLimiter::availableIn($key);

            return response()->view('errors.429', [
                'seconds' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, 60);

        /*
        |--------------------------------------------------------------------------
        | Attempt Authentication
        |--------------------------------------------------------------------------
        */

        if (Auth::attempt($credentials, $request->boolean('remember'))) {

            $request->session()->regenerate();

            RateLimiter::clear($key);

            /** @var \App\Models\User $user */
            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | Force Single Session Across Devices
            |--------------------------------------------------------------------------
            */

            Auth::logoutOtherDevices($request->password);

            /*
            |--------------------------------------------------------------------------
            | Update Login Metadata
            |--------------------------------------------------------------------------
            */

            $user->update([
                'last_login' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Security Log
            |--------------------------------------------------------------------------
            */

            $this->logSecurityEvent(
                type: 'successful_login',
                request: $request,
                email: $user->email
            );

            /*
            |--------------------------------------------------------------------------
            | Login Alert Email
            |--------------------------------------------------------------------------
            */

            $this->sendLoginAlert($request, $user->email);

            return redirect()->route('admin.dashboard');
        }

        /*
        |--------------------------------------------------------------------------
        | Failed Authentication
        |--------------------------------------------------------------------------
        */

        $this->logSecurityEvent(
            type: 'failed_login',
            request: $request,
            email: $request->email
        );

        return back()
            ->withErrors([
                'email' => 'Invalid credentials.',
            ])
            ->onlyInput('email');
    }

    /**
     * Handle admin logout.
     */
    public function logout(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Log Logout Event
        |--------------------------------------------------------------------------
        */

        if (Auth::check()) {
            $this->logSecurityEvent(
                type: 'logout',
                request: $request,
                email: Auth::user()->email
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Destroy Session
        |--------------------------------------------------------------------------
        */

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Build unique throttle key.
     */
    private function throttleKey(Request $request): string
    {
        return 'admin-login|' . $request->ip() . '|' . strtolower($request->email);
    }

    /**
     * Store security event.
     */
    private function logSecurityEvent(
        string $type,
        Request $request,
        ?string $email = null
    ): void {
        DB::table('security_events')->insert([
            'type'       => $type,
            'ip'         => $request->ip(),
            'agent'      => $request->userAgent(),
            'email'      => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Send login alert email.
     */
    private function sendLoginAlert(Request $request, string $email): void
    {
        $body =
            "RiskSignal Admin Login Detected\n\n" .
            "Email: {$email}\n" .
            "IP Address: {$request->ip()}\n" .
            "Time: " . now() . "\n" .
            "Browser: {$request->userAgent()}";

        Mail::raw($body, function ($message) use ($email) {
            $message->to('durgeshduklan5@gmail.com')
                ->subject("RiskSignal Admin Login Alert - {$email}");
        });
    }
}