<?php

namespace App\Jobs;

use App\Models\PortfolioFile;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessPortfolioFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | QUEUE SETTINGS
    |--------------------------------------------------------------------------
    */

    public int $timeout = 300;

    public int $tries = 3;

    public int $backoff = 60;

    public int $maxExceptions = 3;

    private const DISK = 'portfolios';

    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'pdf'];

    private const MIME_MAP = [
        'csv'  => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'pdf'  => 'application/pdf',
    ];

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public readonly PortfolioFile $portfolioFile
    ) {}

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        /*
        |--------------------------------------------------------------------------
        | REFRESH MODEL
        |--------------------------------------------------------------------------
        */

        $file = $this->portfolioFile->fresh();

        if (!$file) {

            Log::warning(
                'Portfolio file no longer exists.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE PROCESSING
        |--------------------------------------------------------------------------
        */

        if (
            $file->status === PortfolioFile::STATUS_PROCESSED
        ) {

            Log::info(
                'Portfolio file already processed.',
                [
                    'portfolio_file_id' => $file->id,
                ]
            );

            return;
        }

        try {

            /*
            |------------------------------------------------------------------
            | LOG START
            |------------------------------------------------------------------
            */

            Log::info(
                'Portfolio processing started.',
                [
                    'portfolio_file_id' => $file->id,

                    'user_id' => $file->user_id,

                    'file_name' =>
                    $file->original_name,

                    'attempt' =>
                    $this->attempts(),
                ]
            );

            /*
            |------------------------------------------------------------------
            | UPDATE STATUS
            |------------------------------------------------------------------
            */

            $file->update([

                'status' =>
                PortfolioFile::STATUS_PROCESSING,

                'meta' => array_merge(
                    $file->meta ?? [],
                    [
                        'processing_started_at' =>
                        now()->toIso8601String(),
                    ]
                ),
            ]);

            /*
            |------------------------------------------------------------------
            | VERIFY FILE EXISTS
            |------------------------------------------------------------------
            */

            if (!Storage::disk(self::DISK)->exists($file->path)) {

                throw new \Exception(
                    'Portfolio file missing from storage.'
                );
            }

            /*
            |------------------------------------------------------------------
            | ABSOLUTE FILE PATH
            |------------------------------------------------------------------
            */

            $absolutePath = Storage::disk(self::DISK)
                ->path($file->path);

            /*
            |------------------------------------------------------------------
            | FILE TYPE
            |------------------------------------------------------------------
            */

            $extension = strtolower(
                pathinfo(
                    $absolutePath,
                    PATHINFO_EXTENSION
                )
            );

            /*
            |------------------------------------------------------------------
            | ZIP: EXTRACT AND QUEUE CONTAINED FILES
            |------------------------------------------------------------------
            */

            if ($extension === 'zip') {
                $this->handleZipExtraction($file, $absolutePath);
                return;
            }

            /*
            |------------------------------------------------------------------
            | TODO: ACTUAL PARSING ENGINE
            |------------------------------------------------------------------
            |
            | Future:
            | - PDF parsing
            | - Excel extraction
            | - Holdings extraction
            | - Risk analysis
            | - AI summaries
            |
            */

            sleep(2);

            /*
            |------------------------------------------------------------------
            | UPDATE STATUS SUCCESS
            |------------------------------------------------------------------
            */

            $file->update([

                'status' =>
                PortfolioFile::STATUS_PROCESSED,

                'processed_at' => now(),

                'meta' => array_merge(
                    $file->meta ?? [],
                    [
                        'processing_completed_at' =>
                        now()->toIso8601String(),

                        'extension' => $extension,
                    ]
                ),
            ]);

            /*
            |------------------------------------------------------------------
            | LOG SUCCESS
            |------------------------------------------------------------------
            */

            Log::info(
                'Portfolio processing completed.',
                [
                    'portfolio_file_id' => $file->id,

                    'user_id' => $file->user_id,

                    'file_name' =>
                    $file->original_name,
                ]
            );
        } catch (\Throwable $e) {

            /*
            |------------------------------------------------------------------
            | UPDATE FAILED STATUS
            |------------------------------------------------------------------
            */

            $file->update([

                'status' =>
                PortfolioFile::STATUS_FAILED,

                'meta' => array_merge(
                    $file->meta ?? [],
                    [
                        'failed_at' =>
                        now()->toIso8601String(),

                        'error_message' =>
                        $e->getMessage(),
                    ]
                ),
            ]);

            /*
            |------------------------------------------------------------------
            | LOG ERROR
            |------------------------------------------------------------------
            */

            Log::error(
                'Portfolio processing failed.',
                [
                    'portfolio_file_id' => $file->id,

                    'user_id' => $file->user_id,

                    'file_name' =>
                    $file->original_name,

                    'message' =>
                    $e->getMessage(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ZIP EXTRACTION
    |--------------------------------------------------------------------------
    |
    | Extracts the ZIP archive to a secure temp directory, then for each valid
    | portfolio file inside (CSV/XLSX/XLS/PDF), stores it on the portfolios
    | disk and dispatches a new ProcessPortfolioFile job so it flows through
    | the same processing pipeline as a directly uploaded file.
    |
    */

    private function handleZipExtraction(
        PortfolioFile $file,
        string $absolutePath
    ): void {

        $tempDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'portfolio_zip_'
            . uniqid('', true);

        mkdir($tempDir, 0755, true);

        try {

            /*
            |--------------------------------------------------------------
            | OPEN ARCHIVE
            |--------------------------------------------------------------
            */

            $zip = new \ZipArchive();

            $result = $zip->open($absolutePath);

            if ($result !== true) {

                throw new \Exception(
                    "Failed to open ZIP archive (ZipArchive error code: {$result})."
                );
            }

            $zip->extractTo($tempDir);
            $zip->close();

            /*
            |--------------------------------------------------------------
            | ITERATE EXTRACTED FILES
            |--------------------------------------------------------------
            */

            $extractedCount = 0;
            $skippedCount   = 0;
            $realTempDir    = realpath($tempDir);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $tempDir,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $extractedFile) {

                if ($extractedFile->isDir()) {
                    continue;
                }

                /*
                |----------------------------------------------------------
                | ZIP-SLIP GUARD — ensure path is within temp dir
                |----------------------------------------------------------
                */

                $realFilePath = realpath(
                    $extractedFile->getPathname()
                );

                if (
                    $realFilePath === false
                    || !str_starts_with($realFilePath, $realTempDir)
                ) {
                    $skippedCount++;
                    continue;
                }

                /*
                |----------------------------------------------------------
                | SKIP HIDDEN / SYSTEM FILES
                |----------------------------------------------------------
                */

                $originalName = $extractedFile->getFilename();

                if (str_starts_with($originalName, '.') || str_starts_with($originalName, '__')) {
                    $skippedCount++;
                    continue;
                }

                /*
                |----------------------------------------------------------
                | ALLOWED EXTENSIONS ONLY
                |----------------------------------------------------------
                */

                $ext = strtolower($extractedFile->getExtension());

                if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $skippedCount++;
                    continue;
                }

                /*
                |----------------------------------------------------------
                | STORE ON PORTFOLIOS DISK
                |----------------------------------------------------------
                */

                $directory      = now()->format('Y/m');
                $storedFilename = Str::uuid()->toString() . '.' . $ext;
                $storedPath     = $directory . '/' . $storedFilename;

                Storage::disk(self::DISK)->put(
                    $storedPath,
                    file_get_contents($realFilePath)
                );

                /*
                |----------------------------------------------------------
                | CREATE DATABASE RECORD
                |----------------------------------------------------------
                */

                $childFile = PortfolioFile::create([
                    'user_id'       => $file->user_id,
                    'portfolio_id'  => $file->portfolio_id,
                    'original_name' => $originalName,
                    'stored_name'   => $storedFilename,
                    'path'          => $storedPath,
                    'mime_type'     => self::MIME_MAP[$ext] ?? 'application/octet-stream',
                    'file_size'     => $extractedFile->getSize(),
                    'status'        => PortfolioFile::STATUS_PENDING,
                    'meta'          => [
                        'uploaded_at'             => now()->toIso8601String(),
                        'extension'               => $ext,
                        'extracted_from_zip_id'   => $file->id,
                        'extracted_from_zip_name' => $file->original_name,
                    ],
                ]);

                /*
                |----------------------------------------------------------
                | QUEUE FOR PROCESSING
                |----------------------------------------------------------
                */

                self::dispatch($childFile);

                $extractedCount++;
            }

            /*
            |--------------------------------------------------------------
            | MARK ZIP AS PROCESSED
            |--------------------------------------------------------------
            */

            $file->update([
                'status'       => PortfolioFile::STATUS_PROCESSED,
                'processed_at' => now(),
                'meta'         => array_merge($file->meta ?? [], [
                    'processing_completed_at' => now()->toIso8601String(),
                    'extension'               => 'zip',
                    'extracted_files_count'   => $extractedCount,
                    'skipped_files_count'     => $skippedCount,
                ]),
            ]);

            Log::info('ZIP archive extracted and queued.', [
                'portfolio_file_id' => $file->id,
                'user_id'           => $file->user_id,
                'extracted'         => $extractedCount,
                'skipped'           => $skippedCount,
            ]);
        } finally {

            /*
            |--------------------------------------------------------------
            | CLEANUP TEMP DIRECTORY
            |--------------------------------------------------------------
            */

            $this->cleanupTempDir($tempDir);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CLEANUP TEMP DIRECTORY
    |--------------------------------------------------------------------------
    */

    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {

            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
