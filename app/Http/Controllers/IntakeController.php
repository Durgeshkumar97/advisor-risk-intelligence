<?php

namespace App\Http\Controllers;

use App\Actions\Intakes\QueueAdminFreeTrialLeadNotificationAction;
use App\Actions\Intakes\StoreIfaTrialLeadAction;
use App\Http\Requests\StoreIfaTrialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class IntakeController extends Controller
{
    public function __construct(
        private readonly StoreIfaTrialLeadAction $storeIfaTrialLead,
        private readonly QueueAdminFreeTrialLeadNotificationAction $queueAdminNotification,
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

        Auth::login($result['user']);
        $request->session()->regenerate();
        $result['user']->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('onboarding');
    }
}
