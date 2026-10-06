<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AssembleBundleZip;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\StockRiskService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * An advisor is never shown the text of an exception. When processing fails
 * unexpectedly they see one fixed, plain sentence; the exception itself goes
 * to the log and to the exception handler (and so to Sentry).
 *
 * Before this, the upload history printed the first 200 characters of
 * whatever was thrown — for a database error, the SQLSTATE, the connection
 * and database names and the start of the SQL statement.
 */
class FailureMessageTest extends TestCase
{
    use RefreshDatabase;

    private const PLAIN = 'Processing failed. Please check the file format or contact support.';

    /** Fragments of exception text that must never be stored for, or shown to, an advisor. */
    private const LEAKS = ['SQLSTATE', 'Connection:', 'SQL:', 'insert into', 'portfolio_assets', 'NOT NULL', 'cannot be null', ':memory:', 'Stack trace', '.php'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        $this->user = activeSubscriberUser();
    }

    /** A pending file whose processing fails in the database: holdings cannot be stored without a portfolio. */
    private function fileThatFailsInTheDatabase(): PortfolioFile
    {
        Storage::disk('portfolios')->put('uploads/h.csv', "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n");

        return PortfolioFile::create([
            'user_id' => $this->user->id, 'portfolio_id' => null, 'original_name' => 'h.csv', 'stored_name' => 'h.csv',
            'path' => 'uploads/h.csv', 'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PENDING,
        ]);
    }

    private function process(PortfolioFile $file): void
    {
        try {
            ProcessPortfolioFile::dispatchSync($file);
        } catch (\Throwable) {
            // The job still fails (so the queue retries and records it); what
            // matters here is what it left behind for the advisor.
        }
    }

    private function assertNoLeak(string $text, string $where): void
    {
        $databaseName = (string) config('database.connections.'.config('database.default').'.database');

        foreach ([...self::LEAKS, $databaseName] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $text, "{$where} contains \"{$leak}\"");
        }
    }

    /** The upload-history table as the advisor sees it (the table only, not the page's scripts and links). */
    private function historyPage(): string
    {
        $page = $this->actingAs($this->user)->get(route('portfolio.upload'))->getContent();
        $start = strpos($page, '<table', strpos($page, 'Upload History'));

        return html_entity_decode(substr($page, $start, strpos($page, '</table>', $start) - $start), ENT_QUOTES);
    }

    // ─── what is stored ──────────────────────────────────────────────────

    public function test_a_job_that_fails_in_the_database_stores_the_plain_message_and_nothing_of_the_exception(): void
    {
        $file = $this->fileThatFailsInTheDatabase();

        $this->process($file);
        $file = $file->fresh();

        $this->assertSame(PortfolioFile::STATUS_FAILED, $file->status);
        $this->assertSame(self::PLAIN, $file->meta['error_message']);
        $this->assertNoLeak(json_encode($file->meta), 'the stored file record');
        $this->assertSame(0, PortfolioAsset::count());
    }

    public function test_the_exception_still_reaches_the_log_and_the_exception_handler(): void
    {
        Exceptions::fake();
        Log::spy();

        $this->process($this->fileThatFailsInTheDatabase());

        Exceptions::assertReported(fn (QueryException $e) => str_contains($e->getMessage(), 'portfolio_assets'));
        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context) => $message === 'ProcessPortfolioFile: failed.'
                && str_contains($context['message'], 'SQLSTATE')
                && ! empty($context['trace'])
        )->once();
    }

    public function test_an_exception_reported_by_the_job_and_again_by_the_queue_worker_is_reported_once(): void
    {
        // The job reports and rethrows; the worker reports what it catches.
        $reports = 0;
        app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\RuntimeException $e) use (&$reports) {
            $reports++;

            return false;
        });

        $exception = new \RuntimeException('thrown once');

        report($exception);
        report($exception);

        $this->assertSame(1, $reports);
    }

    // ─── what is shown ───────────────────────────────────────────────────

    public function test_the_upload_history_shows_the_plain_message_for_a_file_that_failed_in_the_database(): void
    {
        $this->process($this->fileThatFailsInTheDatabase());

        $page = $this->historyPage();

        $this->assertStringContainsString(self::PLAIN, $page);
        $this->assertNoLeak($page, 'the upload history page');
    }

    public function test_a_file_that_failed_before_this_fix_and_still_holds_exception_text_is_shown_the_plain_message(): void
    {
        // A row as production holds it today: the exception text is in the record.
        PortfolioFile::create([
            'user_id' => $this->user->id, 'original_name' => 'old.csv', 'stored_name' => 'old.csv', 'path' => 'uploads/old.csv',
            'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_FAILED,
            'meta' => ['error_message' => "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'portfolio_id' cannot be null (Connection: mysql, Database: u000000000_example, SQL: insert into `portfolio_assets` (`portfolio_id`, `asset_type`) values (?, mutual_fund))"],
        ]);

        $page = $this->historyPage();

        $this->assertStringContainsString(self::PLAIN, $page);
        $this->assertNoLeak($page, 'the upload history page');
        $this->assertStringNotContainsString('u000000000_example', $page);
    }

    public function test_a_reason_written_for_the_advisor_is_still_shown_as_written(): void
    {
        Storage::disk('portfolios')->put('uploads/noheader.csv', "just,some\nvalues,here\n");
        $portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Client A']);
        $file = PortfolioFile::create([
            'user_id' => $this->user->id, 'portfolio_id' => $portfolio->id, 'original_name' => 'noheader.csv', 'stored_name' => 'noheader.csv',
            'path' => 'uploads/noheader.csv', 'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PENDING,
        ]);

        $this->process($file);

        $this->assertSame(PortfolioParser::NO_HEADER_MESSAGE, $file->fresh()->meta['error_message']);
        $this->assertSame(PortfolioParser::NO_HEADER_MESSAGE, $file->fresh()->failureMessage());
        $this->assertStringContainsString('Could not find the column headers', $this->historyPage());
    }

    // ─── telling the two apart ───────────────────────────────────────────

    public function test_exception_text_is_recognised_and_every_message_written_for_advisors_is_left_alone(): void
    {
        $failed = fn (?string $message) => new PortfolioFile(['status' => PortfolioFile::STATUS_FAILED, 'meta' => ['error_message' => $message]]);

        $exceptionText = [
            "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'portfolio_id' cannot be null (Connection: mysql, Database: u000000000_example, SQL: insert into `portfolio_assets` …)",
            'SQLSTATE[HY000] [2002] Connection refused',
            'Call to a member function first() on null',
            'Undefined array key "rows"',
            'App\\Services\\RiskEngine\\PortfolioParser::parse(): Argument #1 ($portfolioFile) must be of type App\\Models\\PortfolioFile, null given, called in /home/u000000000/domains/example.in/project/app/Jobs/ProcessPortfolioFile.php on line 130',
            'file_get_contents(/home/u000000000/domains/example.in/project/storage/app/private/portfolios/2026/10/x.csv): Failed to open stream: No such file or directory',
            'Portfolio file missing from storage: 2026/10/3f0c.csv',
            'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
            'Maximum execution time of 60 seconds exceeded',
        ];

        foreach ($exceptionText as $text) {
            $this->assertSame(self::PLAIN, $failed($text)->failureMessage(), $text);
        }

        $writtenForAdvisors = [
            PortfolioParser::NO_HEADER_MESSAGE,
            PortfolioParser::NO_MARKET_VALUE_MESSAGE,
            PortfolioParser::MAX_ROWS_MESSAGE,
            PortfolioParser::NO_FX_RATE_MESSAGE,
            sprintf(PortfolioParser::EXPIRED_FX_RATE_MESSAGE, '30 Aug 2026'),
            sprintf(PortfolioParser::UNSUPPORTED_MESSAGE, 'pdf'),
            'File appears to be empty.',
            (new \ReflectionClassConstant(ProcessPortfolioFile::class, 'NO_HOLDINGS_MESSAGE'))->getValue(),
            'Portfolio contains too many distinct stock symbols (501). Maximum allowed is 500 — please split into smaller batches.',
            'This ZIP has 1,001 clients; the maximum is 1,000 per upload — please split it into smaller batches.',
            'No valid client files found in ZIP archive.',
            'No report was produced for this client.',
            'Duplicate of holdings.csv (identical holdings)',
            "None of this client's 2 files could be used: a.csv — File is empty (0 bytes); b.csv — Unsupported file type: .txt",
            self::PLAIN,
        ];

        foreach ($writtenForAdvisors as $text) {
            $this->assertSame($text, $failed($text)->failureMessage(), $text);
        }

        $this->assertNull($failed(null)->failureMessage());
    }

    // ─── the ZIP bundle ──────────────────────────────────────────────────

    public function test_a_bundle_that_fails_to_assemble_stores_the_plain_message(): void
    {
        $parent = PortfolioFile::create([
            'user_id' => $this->user->id, 'original_name' => 'clients.zip', 'stored_name' => 'clients.zip', 'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PROCESSING, 'meta' => ['extension' => 'zip'],
        ]);
        $portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Asha Rao']);
        // A processed child whose stored client name is not text: building the
        // bundle's file name for it throws part-way through.
        Storage::disk('portfolios')->put('reports/a.pdf', '%PDF-test');
        PortfolioFile::create([
            'user_id' => $this->user->id, 'portfolio_id' => $portfolio->id, 'original_name' => 'a.csv', 'stored_name' => 'a.csv', 'path' => 'uploads/a.csv',
            'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PROCESSED, 'report_path' => 'reports/a.pdf',
            'meta' => ['extracted_from_zip_id' => $parent->id, 'client_name' => ['not', 'text'], 'client_folder' => true],
        ]);

        Exceptions::fake();

        try {
            AssembleBundleZip::dispatchSync($parent->id);
        } catch (\Throwable) {
        }

        $parent = $parent->fresh();

        $this->assertSame(PortfolioFile::STATUS_FAILED, $parent->status);
        $this->assertSame(self::PLAIN, $parent->meta['error_message']);
        $this->assertNoLeak(json_encode($parent->meta), 'the stored ZIP record');
        Exceptions::assertReported(\ErrorException::class);
    }

    public function test_the_bundle_summary_never_prints_exception_text_for_a_client_that_failed(): void
    {
        $parent = PortfolioFile::create([
            'user_id' => $this->user->id, 'original_name' => 'clients.zip', 'stored_name' => 'clients.zip', 'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PROCESSING, 'meta' => ['extension' => 'zip'],
        ]);
        $portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Asha Rao']);
        PortfolioFile::create([
            'user_id' => $this->user->id, 'portfolio_id' => $portfolio->id, 'original_name' => 'a.csv', 'stored_name' => 'a.csv', 'path' => 'uploads/a.csv',
            'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_FAILED,
            'meta' => ['extracted_from_zip_id' => $parent->id, 'client_name' => 'Asha Rao', 'client_folder' => true,
                'error_message' => 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded (Connection: mysql, Database: u000000000_example, SQL: insert into `risk_scores` …)'],
        ]);

        AssembleBundleZip::dispatchSync($parent->id);

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('portfolios')->path($parent->fresh()->bundle_report_path));
        $summary = $zip->getFromName('_SUMMARY.txt');
        $zip->close();

        $this->assertStringContainsString('Asha Rao: '.self::PLAIN, $summary);
        $this->assertNoLeak($summary, '_SUMMARY.txt');
    }
}
