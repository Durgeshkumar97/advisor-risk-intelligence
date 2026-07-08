<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Password::sendResetLink() still only emails a real link to an
        // existing, non-throttled account — that underlying behavior is
        // untouched. What changes is the response: RESET_LINK_SENT,
        // INVALID_USER, and RESET_THROTTLED are deliberately NOT reflected
        // back to the client, since Laravel's default messages for those
        // differ per status and let /forgot-password be used to enumerate
        // which emails have an account.
        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with(
            'status',
            'If an account exists for this email, a password reset link has been sent.'
        );
    }
}
