<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW LOGIN
    |--------------------------------------------------------------------------
    */

    public function showLogin(): \Illuminate\View\View
    {
        // Already authenticated users have no business on the login page
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN  (POST /login)
    |--------------------------------------------------------------------------
    |
    | Rate-limited per IP + email combination to prevent brute-force attacks.
    | Uses Auth::attempt() which runs constant-time password comparison.
    | Session is regenerated on success to prevent session fixation.
    |
    */

    public function login(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. RATE LIMITING — keyed on IP + email to slow targeted attacks
        |--------------------------------------------------------------------------
        */

        $rateKey = 'login:' . Str::lower($validated['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {

            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. ATTEMPT LOGIN
        |--------------------------------------------------------------------------
        */

        if (! Auth::attempt($validated, $request->boolean('remember'))) {

            RateLimiter::hit($rateKey, 300); // decay: 5 minutes

            Log::warning('Auth: failed login attempt', [
                'email' => $validated['email'],
                'ip'    => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. SUCCESS — regenerate session (prevents session fixation)
        |--------------------------------------------------------------------------
        */

        RateLimiter::clear($rateKey);

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $user->forceFill(['last_login_at' => now()])->save();

        Log::info('Auth: user logged in', [
            'user_id' => $user->id,
            'ip'      => $request->ip(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. REDIRECT — send to onboarding if not yet completed
        |--------------------------------------------------------------------------
        */

        if (! $user->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        return redirect()->intended(route('dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW REGISTER
    |--------------------------------------------------------------------------
    */

    public function showRegister(): \Illuminate\View\View
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTER  (POST /register)
    |--------------------------------------------------------------------------
    |
    | Enforces a strong password policy via Laravel's Password rule object.
    | Session is regenerated after login to prevent session fixation.
    |
    */

    public function register(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. RATE LIMITING — prevent account creation spam
        |--------------------------------------------------------------------------
        */

        $rateKey = 'register:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {

            throw ValidationException::withMessages([
                'email' => __('Too many registration attempts. Please try again later.'),
            ]);
        }

        RateLimiter::hit($rateKey, 600); // decay: 10 minutes

        /*
        |--------------------------------------------------------------------------
        | 2. VALIDATION
        |--------------------------------------------------------------------------
        |
        | Password::defaults() enforces the app-wide password policy configured
        | in AppServiceProvider (min 8 chars, mixed case, numbers, symbols).
        | Fall back to a sensible minimum if defaults are not configured.
        |
        */

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. CREATE USER
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Log::info('Auth: new user registered', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $request->ip(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4. LOG IN + REGENERATE SESSION
        |--------------------------------------------------------------------------
        */

        Auth::login($user);

        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | 5. REDIRECT — new users always go to onboarding
        |--------------------------------------------------------------------------
        */

        return redirect()->route('onboarding');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT  (POST /logout)
    |--------------------------------------------------------------------------
    |
    | Invalidating the session + regenerating the CSRF token prevents a
    | logged-out user's old session ID from being reused (session fixation /
    | CSRF token reuse attacks).
    |
    */

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        Log::info('Auth: user logged out', [
            'user_id' => $userId,
            'ip'      => $request->ip(),
        ]);

        return redirect('/');
    }
}
