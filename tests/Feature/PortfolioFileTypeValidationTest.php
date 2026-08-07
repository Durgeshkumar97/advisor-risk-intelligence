<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\PortfolioFileType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/../Support/SharedFixtures.php';

// ─── file-level helpers ───────────────────────────────────────────────────────

function portfolioFileTypeUpload(string $content, string $originalName): UploadedFile
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $tmpPath = tempnam(sys_get_temp_dir(), 'porttype_').'.'.$extension;
    file_put_contents($tmpPath, $content);

    return new UploadedFile($tmpPath, $originalName, null, null, true);
}

function portfolioFileTypeMinimalZip(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'zip_').'.zip';
    $zip = new \ZipArchive;
    $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('holding.csv', "name,asset_type,current_value\nAlice,stock,1000\nBob,stock,2000\nCarol,stock,3000\n");
    $zip->close();

    $content = file_get_contents($tmp);
    unlink($tmp);

    return $content;
}

// ─── 1. the case the fix exists for ───────────────────────────────────────────

describe('portfolio upload endpoint accepts realistic content, rejects disguised payloads', function () {

    it("accepts a single-row CSV in this codebase's real upload format — the exact case the fix exists for", function () {
        Storage::fake('portfolios');
        Queue::fake();

        $user = activeSubscriberUser();
        $file = portfolioFileTypeUpload(
            "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
            'my_portfolio.csv'
        );

        $response = $this->actingAs($user)->post(route('portfolio.upload.store'), ['file' => $file]);

        $response->assertSessionDoesntHaveErrors('file');
        $response->assertRedirect(route('portfolio.upload'));

        $this->assertDatabaseHas('portfolio_files', ['original_name' => 'my_portfolio.csv']);
    });

    // ─── 2. everything that already worked still works, byte-for-byte ────────

    it('validates xlsx, pdf, zip, and disguised non-csv payloads identically to the old mimes: rule (parity check)', function () {
        $samples = [
            'valid xlsx' => [minimalValidXlsxContent(), 'file.xlsx'],
            'valid pdf' => ["%PDF-1.4\n%useless\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>", 'file.pdf'],
            'valid zip' => [portfolioFileTypeMinimalZip(), 'file.zip'],
            'random binary as xlsx' => [random_bytes(200), 'file.xlsx'],
            'random binary as xls' => [random_bytes(200), 'file.xls'],
            'php payload as pdf' => ['<?php echo 1; ?>', 'file.pdf'],
            'multi-row csv (5 rows)' => ["name,asset_type,current_value\nA,stock,1\nB,stock,2\nC,stock,3\nD,stock,4\nE,stock,5\n", 'file.csv'],
        ];

        foreach ($samples as $label => [$content, $name]) {
            $file = portfolioFileTypeUpload($content, $name);

            $oldFails = Validator::make(
                ['file' => $file],
                ['file' => 'mimes:pdf,csv,xlsx,xls,zip']
            )->fails();

            $newFails = Validator::make(
                ['file' => $file],
                ['file' => new PortfolioFileType]
            )->fails();

            expect($newFails)->toBe($oldFails, sprintf(
                'Mismatch for [%s]: old rule %s, new rule %s',
                $label,
                $oldFails ? 'rejected' : 'accepted',
                $newFails ? 'rejected' : 'accepted',
            ));

            @unlink($file->getRealPath());
        }
    });

    // ─── 3. malicious payload disguised with a legitimate csv extension ──────

    it('still rejects a PHP payload disguised with a .csv extension, through the new Rule class, end-to-end', function () {
        Storage::fake('portfolios');
        Queue::fake();

        $user = activeSubscriberUser();
        $file = portfolioFileTypeUpload("<?php system(\$_GET['cmd']); ?>", 'evil.csv');

        $response = $this->actingAs($user)->post(route('portfolio.upload.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('portfolio_files', ['original_name' => 'evil.csv']);
    });

    // ─── 4. a failed/invalid upload must not crash the rule ──────────────────

    it('converts an invalid UploadedFile (failed upload, e.g. oversized) into a clean validation failure instead of throwing', function () {
        // Reproduces the exact failure mode found during investigation:
        // PHP flags the upload as failed (UPLOAD_ERR_INI_SIZE, as it would
        // for a file exceeding upload_max_filesize/post_max_size), but the
        // resulting UploadedFile's getPath() is still a non-empty directory
        // ('/tmp'), not '', so the rule's isValidFileInstance()-style guard
        // doesn't catch it and execution reaches guessExtension()/
        // getMimeType() on a file that doesn't actually exist/isn't readable.
        $file = new UploadedFile(
            '/tmp/nonexistent_failed_upload_'.uniqid(),
            'portfolio.csv',
            'text/csv',
            UPLOAD_ERR_INI_SIZE,
            true
        );

        expect($file->isValid())->toBeFalse();
        expect($file->getPath())->not->toBe('');

        $validator = Validator::make(
            ['file' => $file],
            ['file' => new PortfolioFileType]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('file'))
            ->toBe('Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.');
    });

    // ─── 5. shared contentMatchesAllowedType() is exception-safe generically ─
    //
    // A side effect of extracting this logic out of PortfolioFileType into a
    // shared static method: ProcessPortfolioFile::handleZipExtraction() now
    // gets the same exception-safety net PortfolioFileType already had,
    // instead of only the primary upload path being protected. Testing the
    // shared method directly (not through either specific caller) proves
    // this holds regardless of which one calls it.

    it('contentMatchesAllowedType() reports unacceptable rather than throwing for an unreadable file', function () {
        $file = new \Symfony\Component\HttpFoundation\File\File(
            '/tmp/nonexistent_'.uniqid().'.csv',
            false
        );

        $result = PortfolioFileType::contentMatchesAllowedType($file, 'csv', ['csv', 'xlsx', 'xls', 'pdf']);

        expect($result['acceptable'])->toBeFalse();
        expect($result['detectedExtension'])->toBeNull();
        expect($result['detectedMimeType'])->toBeNull();
    });
});
