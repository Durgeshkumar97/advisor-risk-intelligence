<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (!Auth::attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $request->boolean('remember')
        )) {
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

            Auth::logout();
            $request->session()->invalidate();

            return back()
                ->withErrors(['email' => 'Access denied. Admin account required.'])
                ->withInput($request->only('email'));
        }

        /*
        |--------------------------------------------------------------------------
        | REGENERATE SESSION
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | RECORD LAST LOGIN
        |--------------------------------------------------------------------------
        */

        Auth::user()->forceFill([
            'last_login_at' => now(),
        ])->save();

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
