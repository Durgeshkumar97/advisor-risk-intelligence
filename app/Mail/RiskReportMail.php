<?php

namespace App\Mail;

use App\Models\PortfolioFile;
use App\Models\RiskScore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiskReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PortfolioFile $portfolioFile,
        public readonly RiskScore $riskScore
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->portfolioFile->portfolio?->name ?? $this->portfolioFile->original_name;

        return new Envelope(subject: 'Your Risk Report — ' . $name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.risk-report');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('portfolios', $this->portfolioFile->report_path)
                ->as('RiskSignal-Risk-Report.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
