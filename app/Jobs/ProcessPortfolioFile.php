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
}
