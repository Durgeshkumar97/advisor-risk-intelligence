<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminFreeTrialLeadSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $advisorName,
        public readonly string $whatsapp,
        public readonly string $email,
        public readonly string $firmName,
        public readonly string $submittedAt,
        public readonly string $ipAddress,
        public readonly string $submissionUuid,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New RiskSignal free trial lead',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.free-trial-lead-submitted',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
