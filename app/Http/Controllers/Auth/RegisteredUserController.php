<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountRecoveryService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, UserAccountRecoveryService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = Str::lower($validated['email']);
        $existingUser = User::withTrashed()
            ->where('email', $email)
            ->first();

        if ($existingUser !== null && ! $existingUser->trashed()) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        $password = Hash::make($validated['password']);

        $result = $accounts->findRestoreOrCreateUserByEmail(
            $email,
            [
                'name' => $validated['name'],
                'password' => $password,
            ],
            [
                // Never accept a password from an unauthenticated registration
                // attempt for an account that already exists — the guard above
                // only blocks ACTIVE emails, so this restoreAttributes array is
                // reached when restoring a SOFT-DELETED account. Gating
                // Auth::login() below is not enough on its own: if an
                // attacker-chosen password were written here, they could just
                // log in afterward via the normal, genuinely password-verified
                // /login form. Omitting 'password' keeps the account's
                // original password intact.
                'name' => $validated['name'],
            ],
        );

        $user = $result['user'];

        if (! $result['created']) {
            // Restoring an existing (soft-deleted) account — never auto-login
            // and never accept a new password from this unauthenticated flow.
            // The real owner is notified via a single-use login link instead.
            $accounts->sendLoginLinkToExistingUser($user);

            return redirect()->route('login')
                ->with('success', "An account with this email already exists — we've sent a secure login link to your email.");
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
