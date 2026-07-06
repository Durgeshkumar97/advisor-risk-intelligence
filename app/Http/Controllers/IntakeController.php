<?php

namespace App\Http\Controllers;

use App\Actions\Intakes\QueueAdminFreeTrialLeadNotificationAction;
use App\Actions\Intakes\StoreIfaTrialLeadAction;
use App\Http\Requests\StoreIfaTrialRequest;
use App\Services\UserAccountRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class IntakeController extends Controller
{
    public function __construct(
        private readonly StoreIfaTrialLeadAction $storeIfaTrialLead,
        private readonly QueueAdminFreeTrialLeadNotificationAction $queueAdminNotification,
        private readonly UserAccountRecoveryService $accounts,
    ) {}

    public function ifaSubmit(StoreIfaTrialRequest $request): RedirectResponse
    {
        $result = $this->storeIfaTrialLead->execute(
            validated: $request->validated(),
            document: $request->file('document'),
        );

        if ($result === null) {
            return redirect()->route('login')
                ->with('success', 'You already have a trial. Please login to continue.');
        }

        $this->queueAdminNotification->execute(
            intake: $result['intake'],
            ipAddress: $request->ip(),
        );

        // Never auto-login on an email match to an EXISTING account — the
        // submitter of this unauthenticated form could be anyone who typed
        // in that email, not its owner. Only a genuinely new account is safe
        // to log straight into.
        if (! $result['created']) {
            $this->accounts->sendLoginLinkToExistingUser($result['user']);

            return redirect()->route('login')
                ->with('success', "An account with this email already exists — we've sent a secure login link to your email.");
        }

        Auth::login($result['user']);
        $request->session()->regenerate();
        $result['user']->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('onboarding');
    }
}
