<?php

namespace App\Http\Controllers;

use App\Actions\Intakes\QueueAdminFreeTrialLeadNotificationAction;
use App\Actions\Intakes\StoreIfaTrialLeadAction;
use App\Http\Requests\StoreIfaTrialRequest;
use Illuminate\Http\RedirectResponse;

class IntakeController extends Controller
{
    public function __construct(
        private readonly StoreIfaTrialLeadAction $storeIfaTrialLead,
        private readonly QueueAdminFreeTrialLeadNotificationAction $queueAdminNotification,
    ) {}

    public function ifaSubmit(StoreIfaTrialRequest $request): RedirectResponse
    {
        $intake = $this->storeIfaTrialLead->execute(
            validated: $request->validated(),
            document: $request->file('document'),
        );

        if ($intake === null) {
            return redirect()->route('login')
                ->with('success', 'You already have a trial. Please login to continue.');
        }

        $this->queueAdminNotification->execute(
            intake: $intake,
            ipAddress: $request->ip(),
        );

        return redirect()->route('login')
            ->with('success', 'Trial started! Check your email and login to access your dashboard.');
    }
}
