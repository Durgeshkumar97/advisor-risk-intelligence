<?php

namespace App\Services;

use App\Events\PortfolioFileUploaded;
use App\Exceptions\PortfolioUploadException;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Rules\PortfolioFileType;
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

                if (! $portfolioExists) {

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

                'original_name' => $file->getClientOriginalName(),

                'stored_name' => basename($storedPath),

                'path' => $storedPath,

                'mime_type' => $file->getMimeType(),

                'file_size' => $file->getSize(),

                'status' => PortfolioFile::STATUS_PENDING,

                'meta' => [

                    'uploaded_at' => now()->toIso8601String(),

                    // Same sanitised value the file is stored under, so meta
                    // never claims an extension the file does not have.
                    'extension' => $this->safeExtension($file),
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

                    'portfolio_file_id' => $portfolioFile->id,

                    'user_id' => $userId,

                    'portfolio_id' => $portfolioId,

                    'original_name' => $file->getClientOriginalName(),

                    'stored_path' => $storedPath,
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

                    'portfolio_id' => $portfolioId,

                    'file_name' => $file->getClientOriginalName(),

                    'message' => $e->getMessage(),

                    'trace' => $e->getTraceAsString(),
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

    /*
    |--------------------------------------------------------------------------
    | SAFE EXTENSION
    |--------------------------------------------------------------------------
    |
    | getClientOriginalExtension() is derived from the client-supplied filename
    | and is entirely attacker-controlled. PortfolioFileType validates the file
    | CONTENT, not the claimed extension, so a genuine PDF named `payload.php`
    | passes validation — and the stored filename was built straight from that
    | claimed extension, writing `<uuid>.php` to disk.
    |
    | Not exploitable on its own: the portfolios disk roots outside the webroot
    | (config/filesystems.php) and every download goes through FileController
    | behind auth. This is defence in depth, so an unrelated future change —
    | serving the disk, an LFI, a misconfigured vhost — cannot turn stored
    | uploads into executable content.
    |
    | Anything not on the allow-list is stored as .bin rather than rejected:
    | validation has already accepted the content, so the upload should still
    | succeed; it just must not keep an attacker-chosen extension.
    |
    */

    private function safeExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, PortfolioFileType::ALLOWED_EXTENSIONS, true)
            ? $extension
            : 'bin';
    }

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

                .'.'

                .$this->safeExtension($file);

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

                    'file_name' => $file->getClientOriginalName(),

                    'message' => $e->getMessage(),
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

                    'message' => $e->getMessage(),
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

                'portfolio_file_id' => $portfolioFile->id,

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

            status: PortfolioFile::STATUS_FAILED,

            meta: [

                'failed_at' => now()->toIso8601String(),

                'error_message' => $errorMessage,
            ],
        );
    }
}
