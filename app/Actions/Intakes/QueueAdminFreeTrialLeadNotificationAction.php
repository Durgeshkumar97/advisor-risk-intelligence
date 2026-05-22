<?php

namespace App\Actions\Intakes;

use App\Mail\AdminFreeTrialLeadSubmittedMail;
use App\Models\ClientIntake;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class QueueAdminFreeTrialLeadNotificationAction
{
    public function execute(ClientIntake $intake, ?string $ipAddress): void
    {
        $adminEmail = $this->adminEmail();

        if ($adminEmail === null) {
            Log::warning('IFA lead admin email notification skipped.', [
                'client_intake_id' => $intake->getKey(),
                'submission_uuid' => $intake->submission_uuid,
                'reason' => 'admin_email_not_configured',
            ]);

            return;
        }

        $queue = $this->queueName();

        Mail::to($adminEmail)->queue(
            (new AdminFreeTrialLeadSubmittedMail(
                advisorName: (string) $intake->name,
                whatsapp: (string) $intake->whatsapp,
                email: (string) $intake->email,
                firmName: (string) $intake->firm_name,
                submittedAt: $intake->created_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                ipAddress: $ipAddress ?: 'Unavailable',
                submissionUuid: (string) $intake->submission_uuid,
            ))->onQueue($queue)
        );

        Log::info('IFA lead admin email queued.', [
            'client_intake_id' => $intake->getKey(),
            'submission_uuid' => $intake->submission_uuid,
            'admin_email_hash' => hash('sha256', Str::lower($adminEmail)),
            'queue' => $queue,
        ]);
    }

    private function adminEmail(): ?string
    {
        $email = config('risksignal.lead_notifications.admin_email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return trim($email);
    }

    private function queueName(): string
    {
        $queue = config('risksignal.lead_notifications.queue', 'mail');

        if (! is_string($queue) || trim($queue) === '') {
            return 'mail';
        }

        return trim($queue);
    }
}
