<?php

namespace App\Mail;

use App\Models\PortfolioFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BundleReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PortfolioFile $portfolioFile) {}

    public function envelope(): Envelope
    {
        $name = $this->portfolioFile->original_name ?? 'Multi-Client Bundle';

        return new Envelope(subject: 'All Client Reports — '.$name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bundle-report');
    }

    public function attachments(): array
    {
        $base = pathinfo($this->portfolioFile->original_name ?? '', PATHINFO_FILENAME);
        $safeBase = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base), '_') ?: 'reports';
        $filename = $safeBase.'_reports_'.now()->format('Y-m-d').'.zip';

        return [
            Attachment::fromStorageDisk('portfolios', $this->portfolioFile->bundle_report_path)
                ->as($filename)
                ->withMime('application/zip'),
        ];
    }
}
