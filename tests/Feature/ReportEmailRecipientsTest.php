<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AssembleBundleZip;
use App\Jobs\ProcessPortfolioFile;
use App\Mail\BundleReportMail;
use App\Mail\RiskReportMail;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A generated report contains an end client's holdings and values. It may go
 * to the advisor who owns the account (their email_reports preference) and to
 * nobody else — in particular not to an operator/founder mailbox, which used
 * to receive a copy of every report and every bundle.
 */
class ReportEmailRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_ADDRESS = 'founder@risksignal.in';

    private function advisor(bool $emailReports): User
    {
        return User::factory()->create(['email_reports' => $emailReports]);
    }

    private function processSingleUpload(User $user): void
    {
        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->andReturn([]);

        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);
        $csv = "name,asset_type,invested_value,current_value\nGilt Fund,bond,100000,104000\n";
        Storage::disk('portfolios')->put('uploads/client-a.csv', $csv);

        $file = PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'client-a.csv',
            'stored_name' => 'client-a.csv',
            'path' => 'uploads/client-a.csv',
            'mime_type' => 'text/csv',
            'file_size' => strlen($csv),
            'status' => PortfolioFile::STATUS_PENDING,
        ]);

        ProcessPortfolioFile::dispatchSync($file);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->fresh()->status);
    }

    private function assembleBundle(User $user): void
    {
        Storage::fake('portfolios');
        Mail::fake();

        $parent = PortfolioFile::create([
            'user_id' => $user->id,
            'original_name' => 'clients.zip',
            'stored_name' => 'clients.zip',
            'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip',
            'file_size' => 500,
            'status' => PortfolioFile::STATUS_PROCESSING,
            'meta' => ['extension' => 'zip'],
        ]);

        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client B']);
        Storage::disk('portfolios')->put('reports/client_b.pdf', '%PDF-1.4 Client B');

        PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'Client B.csv',
            'stored_name' => 'Client B.csv',
            'path' => 'uploads/Client B.csv',
            'report_path' => 'reports/client_b.pdf',
            'mime_type' => 'text/csv',
            'file_size' => 50,
            'status' => PortfolioFile::STATUS_PROCESSED,
            'meta' => ['extracted_from_zip_id' => $parent->id, 'client_name' => 'Client B'],
        ]);

        AssembleBundleZip::dispatchSync($parent->id);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $parent->fresh()->status);
    }

    public function test_a_single_report_is_mailed_only_to_the_advisor_who_owns_it(): void
    {
        $user = $this->advisor(emailReports: true);

        $this->processSingleUpload($user);

        Mail::assertQueued(RiskReportMail::class, 1);
        Mail::assertQueued(RiskReportMail::class, fn (RiskReportMail $mail) => $mail->hasTo($user->email));
        Mail::assertNotQueued(RiskReportMail::class, fn (RiskReportMail $mail) => ! $mail->hasTo($user->email));
        Mail::assertNotQueued(RiskReportMail::class, fn (RiskReportMail $mail) => $mail->hasTo(self::OPERATOR_ADDRESS));
    }

    public function test_no_report_mail_is_sent_to_anyone_when_the_advisor_has_reports_switched_off(): void
    {
        $this->processSingleUpload($this->advisor(emailReports: false));

        Mail::assertNothingQueued();
    }

    public function test_a_bundle_is_mailed_only_to_the_advisor_who_owns_it(): void
    {
        $user = $this->advisor(emailReports: true);

        $this->assembleBundle($user);

        Mail::assertQueued(BundleReportMail::class, 1);
        Mail::assertQueued(BundleReportMail::class, fn (BundleReportMail $mail) => $mail->hasTo($user->email));
        Mail::assertNotQueued(BundleReportMail::class, fn (BundleReportMail $mail) => ! $mail->hasTo($user->email));
        Mail::assertNotQueued(BundleReportMail::class, fn (BundleReportMail $mail) => $mail->hasTo(self::OPERATOR_ADDRESS));
    }

    public function test_no_bundle_mail_is_sent_to_anyone_when_the_advisor_has_reports_switched_off(): void
    {
        $this->assembleBundle($this->advisor(emailReports: false));

        Mail::assertNothingQueued();
    }
}
