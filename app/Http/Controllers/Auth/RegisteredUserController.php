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
                'name' => $validated['name'],
                'password' => $password,
            ],
        );

        $user = $result['user'];

        if ($result['created']) {
            event(new Registered($user));
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
