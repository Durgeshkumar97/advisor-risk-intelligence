<?php

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortfolioZipUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ──────────────────────────────────────────────────────────

    private function activeUser(): User
    {
        $plan = Plan::create([
            'name'                 => 'Team',
            'slug'                 => 'team-' . uniqid(),
            'price'                => 999,
            'duration_days'        => 30,
            'portfolio_limit'      => 100,
            'trial_days'           => 0,
            'is_active'            => true,
            'monthly_client_limit' => 1000,
        ]);

        $user = User::factory()->create();

        Subscription::create([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'starts_at'  => now(),
            'ends_at'    => now()->addDays(30),
            'renewal_at' => now()->addDays(30),
            'provider'   => 'razorpay',
        ]);

        return $user;
    }

    private function buildZip(callable $populate): string
    {
        $path = tempnam(sys_get_temp_dir(), 'testzip_') . '.zip';
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $populate($zip);
        $zip->close();

        return $path;
    }

    private function makeZipPortfolioFile(string $zipRealPath): PortfolioFile
    {
        Storage::fake('portfolios');
        Mail::fake();

        $user      = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test Portfolio']);

        $storedPath = 'uploads/clients.zip';
        Storage::disk('portfolios')->put($storedPath, file_get_contents($zipRealPath));

        return PortfolioFile::create([
            'user_id'       => $user->id,
            'portfolio_id'  => $portfolio->id,
            'original_name' => 'clients.zip',
            'stored_name'   => 'clients.zip',
            'path'          => $storedPath,
            'mime_type'     => 'application/zip',
            'file_size'     => filesize($zipRealPath),
            'status'        => PortfolioFile::STATUS_PENDING,
        ]);
    }

    private function buildMinimalValidXlsx(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->close();

        $content = file_get_contents($tmp);
        unlink($tmp);

        return $content;
    }

    /**
     * Invokes the private handleZipExtraction() directly (bypassing handle()
     * and the queue dispatch machinery entirely), with Bus::fake() active so
     * the inner Bus::batch($jobs)->dispatch() call for child files is a
     * no-op — the child PortfolioFile rows this test asserts on are already
     * created earlier in the same method, before that dispatch line, so
     * this isolates "did extraction accept/reject each entry correctly"
     * from "did downstream parsing of an xlsx/pdf child succeed", which is
     * a separate, pre-existing concern unrelated to this content-check fix.
     */
    private function runZipExtraction(PortfolioFile $file): void
    {
        $job    = new ProcessPortfolioFile($file);
        $method = new \ReflectionMethod($job, 'handleZipExtraction');
        $method->setAccessible(true);
        $method->invoke($job, $file, Storage::disk('portfolios')->path($file->path));
    }

    // ─── 1. entry-count cap rejected at upload time ──────────────────────

    public function test_zip_with_too_many_entries_is_rejected_at_upload_time_before_any_job(): void
    {
        Storage::fake('portfolios');

        $user = $this->activeUser();

        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            for ($i = 0; $i < 2001; $i++) {
                $zip->addFromString("client_{$i}.csv", "name,value\na,1");
            }
        });

        $upload = new UploadedFile($zipPath, 'clients.zip', 'application/zip', null, true);

        $response = $this->actingAs($user)->post(route('portfolio.upload.store'), [
            'file' => $upload,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString(
            'too many files',
            collect(session('errors')->get('file'))->implode(' ')
        );

        $this->assertDatabaseCount('portfolio_files', 0);

        @unlink($zipPath);
    }

    // ─── 2. oversized uncompressed size rejected (real mini zip-bomb) ────

    public function test_zip_with_huge_uncompressed_size_is_rejected_despite_small_compressed_size(): void
    {
        Storage::fake('portfolios');

        $user = $this->activeUser();

        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            // 510MB of a single repeated byte — DEFLATE compresses this to a
            // tiny fraction of that, so the file on disk stays well under the
            // 20MB upload cap while claiming to decompress past 500MB. This
            // is the actual zip-bomb pattern, not just "a large legit file".
            $zip->addFromString('bomb.csv', str_repeat('A', 510 * 1024 * 1024));
        });

        // Sanity check: the compressed file itself is small (proves this is
        // genuinely a compression-ratio attack, not just a big upload).
        $this->assertLessThan(20 * 1024 * 1024, filesize($zipPath));

        $upload = new UploadedFile($zipPath, 'clients.zip', 'application/zip', null, true);

        $response = $this->actingAs($user)->post(route('portfolio.upload.store'), [
            'file' => $upload,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString(
            'too large once uncompressed',
            collect(session('errors')->get('file'))->implode(' ')
        );

        $this->assertDatabaseCount('portfolio_files', 0);

        @unlink($zipPath);
    }

    // ─── 3. zip-slip entry is never written to disk ──────────────────────
    // NOTE: on this libzip build extractTo() neutralises '../' on its own, so this
    // test can't discriminate the fix from that accident — see 3b for the real proof.

    public function test_zip_slip_entry_is_never_written_outside_the_extraction_directory(): void
    {
        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('good_client.csv', "name,asset_type,current_value\nReliance,stock,10000");
            $zip->addFromString('../evil_marker.txt', 'this must never be written');
        });

        $file = $this->makeZipPortfolioFile($zipPath);

        app()->instance(StockRiskService::class, new class extends StockRiskService {
            public function __construct() {}
            public function classifyBatch(array $symbols): array { return []; }
        });

        $escapeTarget = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evil_marker.txt';
        @unlink($escapeTarget); // in case a prior failed run left it behind

        ProcessPortfolioFile::dispatchSync($file);

        $this->assertFileDoesNotExist($escapeTarget);

        // The legitimate sibling entry in the same archive is still processed.
        $this->assertDatabaseHas('portfolio_files', [
            'original_name' => 'good_client.csv',
        ]);

        @unlink($zipPath);
        @unlink($escapeTarget);
    }

    // ─── 3b. direct proof the validation logic itself is correct ─────────
    //
    // On this specific PHP/libzip build, extractTo() already neutralises
    // every traversal pattern tested (leading ../, absolute paths, deeply
    // nested ../../../../, even a mixed "....//" pattern) — confirmed by
    // direct experimentation, matching what the original audit found. That
    // means the end-to-end test above cannot actually discriminate between
    // "the fix is present" and "the fix is absent" on this environment: it
    // would pass either way, purely by accident of the underlying library.
    // This test proves isUnsafeZipEntryName() itself is correct, independent
    // of whatever a given libzip build happens to also do — the actual
    // point of moving this check into application code in the first place.

    public function test_is_unsafe_zip_entry_name_correctly_classifies_every_pattern(): void
    {
        $job = new ProcessPortfolioFile(new PortfolioFile());

        $method = new \ReflectionMethod($job, 'isUnsafeZipEntryName');
        $method->setAccessible(true);

        $unsafe = [
            '../evil.txt',
            '../../evil.txt',
            '../../../../../../../../tmp/evil.txt',
            'foo/../../evil.txt',
            '/etc/passwd',
            '/tmp/evil_absolute.txt',
            'C:\\evil.txt',
            'C:/evil.txt',
        ];

        $safe = [
            'good_client.csv',
            'foo/bar/baz.csv',
            'clients/2026/alice.csv',
            'file...with...dots.csv',
        ];

        foreach ($unsafe as $name) {
            $this->assertTrue($method->invoke($job, $name), "Expected '{$name}' to be classified unsafe");
        }

        foreach ($safe as $name) {
            $this->assertFalse($method->invoke($job, $name), "Expected '{$name}' to be classified safe");
        }
    }

    // ─── 4. cleanup command ───────────────────────────────────────────────

    public function test_cleanup_command_removes_old_orphaned_dirs_but_not_fresh_ones(): void
    {
        $oldDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portfolio_zip_' . uniqid('old_', true);
        $freshDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portfolio_zip_' . uniqid('fresh_', true);

        mkdir($oldDir, 0755, true);
        mkdir($freshDir, 0755, true);
        file_put_contents($oldDir . '/leftover.csv', 'x');

        touch($oldDir, time() - 7200); // 2 hours old — past the 1-hour cutoff
        // $freshDir keeps its just-created mtime (now)

        try {
            Artisan::call('portfolio:cleanup-temp-dirs');

            $this->assertDirectoryDoesNotExist($oldDir);
            $this->assertDirectoryExists($freshDir);
        } finally {
            if (is_dir($oldDir)) {
                @unlink($oldDir . '/leftover.csv');
                @rmdir($oldDir);
            }
            @rmdir($freshDir);
        }
    }

    // ─── 5. legitimate multi-file zip still processes correctly ──────────

    public function test_legitimate_multi_file_zip_still_extracts_correctly(): void
    {
        // NOTE: no Bus::fake() here — it would intercept dispatchSync() on
        // the outer ProcessPortfolioFile call too (not just the inner
        // per-child batch), silently preventing handle() from running at all.
        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('alice.csv', "name,asset_type,current_value\nHDFC Bank,stock,20000");
            $zip->addFromString('bob.csv', "name,asset_type,current_value\nInfosys,stock,15000");
        });

        $file = $this->makeZipPortfolioFile($zipPath);

        app()->instance(StockRiskService::class, new class extends StockRiskService {
            public function __construct() {}
            public function classifyBatch(array $symbols): array { return []; }
        });

        ProcessPortfolioFile::dispatchSync($file);

        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'alice.csv']);
        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'bob.csv']);

        $children = PortfolioFile::where('meta->extracted_from_zip_id', $file->id)->get();
        $this->assertCount(2, $children);

        @unlink($zipPath);
    }

    // ─── 6. content-based MIME check accepts genuine CSV/XLSX/PDF ────────

    public function test_legitimate_csv_xlsx_and_pdf_entries_in_a_zip_are_all_accepted(): void
    {
        Bus::fake();

        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('alice.csv', "name,asset_type,current_value\nHDFC Bank,stock,20000");
            $zip->addFromString('bob.xlsx', $this->buildMinimalValidXlsx());
            $zip->addFromString('carol.pdf', "%PDF-1.4\n%useless\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>");
        });

        $file = $this->makeZipPortfolioFile($zipPath);

        $this->runZipExtraction($file);

        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'alice.csv']);
        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'bob.xlsx']);
        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'carol.pdf']);

        $children = PortfolioFile::where('meta->extracted_from_zip_id', $file->id)->get();
        $this->assertCount(3, $children);

        // Content-detection legitimately lands on either text/csv or
        // text/plain for real CSV rows depending on shape — both are the
        // accepted outcomes for a .csv-claimed entry (see the carve-out in
        // ProcessPortfolioFile::handleZipExtraction()).
        $this->assertContains(
            $children->firstWhere('original_name', 'alice.csv')->mime_type,
            ['text/csv', 'text/plain']
        );
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $children->firstWhere('original_name', 'bob.xlsx')->mime_type);
        $this->assertSame('application/pdf', $children->firstWhere('original_name', 'carol.pdf')->mime_type);

        @unlink($zipPath);
    }

    // ─── 7. content-based MIME check rejects a disguised payload ─────────

    public function test_a_php_payload_disguised_with_a_csv_extension_is_rejected_and_never_becomes_a_holding(): void
    {
        Bus::fake();

        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('good_client.csv', "name,asset_type,current_value\nReliance,stock,10000");
            $zip->addFromString('evil.csv', "<?php system(\$_GET['cmd']); ?>");
        });

        $file = $this->makeZipPortfolioFile($zipPath);

        $this->runZipExtraction($file);

        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'good_client.csv']);
        $this->assertDatabaseMissing('portfolio_files', ['original_name' => 'evil.csv']);

        $skipReasons = $file->fresh()->meta['skip_reasons'] ?? [];
        $this->assertArrayHasKey('evil.csv', $skipReasons);
        $this->assertStringContainsString('does not match a supported type', $skipReasons['evil.csv']);

        @unlink($zipPath);
    }

    // ─── 8. mixed batch: legitimate entries processed, disguised one skipped ─

    public function test_mixed_zip_processes_legitimate_entries_and_skips_only_the_disguised_one(): void
    {
        Bus::fake();

        $zipPath = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('alice.csv', "name,asset_type,current_value\nHDFC Bank,stock,20000");
            $zip->addFromString('bob.csv', "name,asset_type,current_value\nInfosys,stock,15000");
            $zip->addFromString('evil.csv', "<?php system(\$_GET['cmd']); ?>");
        });

        $file = $this->makeZipPortfolioFile($zipPath);

        $this->runZipExtraction($file);

        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'alice.csv']);
        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'bob.csv']);
        $this->assertDatabaseMissing('portfolio_files', ['original_name' => 'evil.csv']);

        $children = PortfolioFile::where('meta->extracted_from_zip_id', $file->id)->get();
        $this->assertCount(2, $children);

        @unlink($zipPath);
    }
}
