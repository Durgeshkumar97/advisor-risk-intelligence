<?php

namespace App\Services;

use App\Events\PortfolioFileUploaded;
use App\Exceptions\PortfolioUploadException;

use App\Models\Portfolio;
use App\Models\PortfolioFile;

use Illuminate\Http\UploadedFile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

class PortfolioUploadService
{
    /*
    |--------------------------------------------------------------------------
    | STORAGE DISK
    |--------------------------------------------------------------------------
    */

    private const DISK = 'portfolios';

    /*
    |--------------------------------------------------------------------------
    | HANDLE UPLOAD
    |--------------------------------------------------------------------------
    */

    public function handleUpload(
        int $userId,
        UploadedFile $file,
        ?int $portfolioId = null,
    ): PortfolioFile {

        DB::beginTransaction();

        $storedPath = null;

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFY PORTFOLIO OWNERSHIP
            |--------------------------------------------------------------------------
            */

            if ($portfolioId !== null) {

                $portfolioExists = Portfolio::query()

                    ->where('id', $portfolioId)

                    ->where('user_id', $userId)

                    ->exists();

                if (!$portfolioExists) {

                    throw PortfolioUploadException::validationError(
                        'Invalid portfolio selected.',
                        $userId,
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STORE FILE
            |--------------------------------------------------------------------------
            */

            $storedPath = $this->storeFile(
                userId: $userId,
                file: $file,
            );

            /*
            |--------------------------------------------------------------------------
            | CREATE DATABASE RECORD
            |--------------------------------------------------------------------------
            */

            $portfolioFile = PortfolioFile::create([

                'user_id' => $userId,

                'portfolio_id' => $portfolioId,

                'original_name' =>
                    $file->getClientOriginalName(),

                'stored_name' =>
                    basename($storedPath),

                'path' => $storedPath,

                'mime_type' =>
                    $file->getMimeType(),

                'file_size' =>
                    $file->getSize(),

                'status' =>
                    PortfolioFile::STATUS_PENDING,

                'meta' => [

                    'uploaded_at' =>
                        now()->toIso8601String(),

                    'extension' =>
                        strtolower(
                            $file->getClientOriginalExtension()
                        ),
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | COMMIT TRANSACTION
            |--------------------------------------------------------------------------
            */

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | FIRE EVENT
            |--------------------------------------------------------------------------
            */

            event(
                new PortfolioFileUploaded(
                    $portfolioFile
                )
            );

            /*
            |--------------------------------------------------------------------------
            | LOG SUCCESS
            |--------------------------------------------------------------------------
            */

            Log::info(
                'Portfolio file uploaded successfully.',
                [

                    'portfolio_file_id' =>
                        $portfolioFile->id,

                    'user_id' => $userId,

                    'portfolio_id' =>
                        $portfolioId,

                    'original_name' =>
                        $file->getClientOriginalName(),

                    'stored_path' =>
                        $storedPath,
                ]
            );

            return $portfolioFile;

        } catch (\Throwable $e) {

            DB::rollBack();

            /*
            |--------------------------------------------------------------------------
            | CLEANUP STORED FILE
            |--------------------------------------------------------------------------
            */

            if ($storedPath !== null) {

                $this->cleanupFile($storedPath);
            }

            /*
            |--------------------------------------------------------------------------
            | LOG ERROR
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Portfolio upload failed.',
                [

                    'user_id' => $userId,

                    'portfolio_id' =>
                        $portfolioId,

                    'file_name' =>
                        $file->getClientOriginalName(),

                    'message' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | RE-THROW BUSINESS EXCEPTIONS
            |--------------------------------------------------------------------------
            */

            if (
                $e instanceof PortfolioUploadException
            ) {
                throw $e;
            }

            /*
            |--------------------------------------------------------------------------
            | WRAP SYSTEM ERROR
            |--------------------------------------------------------------------------
            */

            throw PortfolioUploadException::storageError(
                'Portfolio upload failed unexpectedly.',
                previous: $e,
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE FILE
    |--------------------------------------------------------------------------
    */

    private function storeFile(
        int $userId,
        UploadedFile $file,
    ): string {

        try {

            /*
            |--------------------------------------------------------------------------
            | DIRECTORY PARTITIONING
            |--------------------------------------------------------------------------
            */

            $directory =
                now()->format('Y/m');

            /*
            |--------------------------------------------------------------------------
            | SECURE RANDOM FILE NAME
            |--------------------------------------------------------------------------
            */

            $filename =
                Str::uuid()->toString()

                . '.'

                . strtolower(
                    $file->getClientOriginalExtension()
                );

            /*
            |--------------------------------------------------------------------------
            | STORE
            |--------------------------------------------------------------------------
            */

            $storedPath = Storage::disk(self::DISK)

                ->putFileAs(
                    $directory,
                    $file,
                    $filename
                );

            if ($storedPath === false) {

                throw new \RuntimeException(
                    'Storage disk did not return a stored file path.'
                );
            }

            return $storedPath;

        } catch (\Throwable $e) {

            Log::error(
                'Failed to store uploaded file.',
                [

                    'user_id' => $userId,

                    'file_name' =>
                        $file->getClientOriginalName(),

                    'message' =>
                        $e->getMessage(),
                ]
            );

            throw PortfolioUploadException::storageError(
                'Failed to store uploaded file.',
                previous: $e,
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CLEANUP FILE
    |--------------------------------------------------------------------------
    */

    private function cleanupFile(
        string $path
    ): void {

        try {

            if (
                Storage::disk(self::DISK)
                    ->exists($path)
            ) {

                Storage::disk(self::DISK)
                    ->delete($path);

                Log::info(
                    'Cleaned up failed upload file.',
                    [
                        'path' => $path,
                    ]
                );
            }

        } catch (\Throwable $e) {

            Log::warning(
                'Failed to cleanup uploaded file.',
                [

                    'path' => $path,

                    'message' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        PortfolioFile $portfolioFile,
        string $status,
        ?array $meta = null,
    ): PortfolioFile {

        $payload = [

            'status' => $status,
        ];

        /*
        |--------------------------------------------------------------------------
        | META MERGE
        |--------------------------------------------------------------------------
        */

        if ($meta !== null) {

            $payload['meta'] = array_merge(

                $portfolioFile->meta ?? [],

                $meta
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PROCESSED TIMESTAMP
        |--------------------------------------------------------------------------
        */

        if (
            $status ===
            PortfolioFile::STATUS_PROCESSED
        ) {

            $payload['processed_at'] = now();
        }

        $portfolioFile->update($payload);

        Log::info(
            'Portfolio file status updated.',
            [

                'portfolio_file_id' =>
                    $portfolioFile->id,

                'status' => $status,
            ]
        );

        return $portfolioFile->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | MARK AS FAILED
    |--------------------------------------------------------------------------
    */

    public function markAsFailed(
        PortfolioFile $portfolioFile,
        string $errorMessage,
    ): PortfolioFile {

        return $this->updateStatus(

            portfolioFile: $portfolioFile,

            status:
                PortfolioFile::STATUS_FAILED,

            meta: [

                'failed_at' =>
                    now()->toIso8601String(),

                'error_message' =>
                    $errorMessage,
            ],
        );
    }
}
