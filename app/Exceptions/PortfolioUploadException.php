<?php

namespace App\Exceptions;

class PortfolioUploadException extends \Exception
{
    /*
    |--------------------------------------------------------------------------
    | CONTEXT DATA
    |--------------------------------------------------------------------------
    */

    public ?int $userId = null;

    public ?string $fileName = null;

    public ?string $storedPath = null;

    public array $context = [];

    /*
    |--------------------------------------------------------------------------
    | FACTORY METHODS
    |--------------------------------------------------------------------------
    */

    public static function uploadFailed(
        string $message,
        ?int $userId = null,
        ?string $fileName = null,
        ?string $storedPath = null,
        array $additionalContext = [],
        ?\Throwable $previous = null,
    ): self {
        $exception = new self($message, 0, $previous);
        $exception->userId = $userId;
        $exception->fileName = $fileName;
        $exception->storedPath = $storedPath;
        $exception->context = array_merge([
            'user_id' => $userId,
            'file_name' => $fileName,
            'stored_path' => $storedPath,
        ], $additionalContext);

        return $exception;
    }

    public static function storageError(
        string $message,
        ?int $userId = null,
        ?string $fileName = null,
        ?\Throwable $previous = null,
    ): self {
        return self::uploadFailed(
            $message,
            $userId,
            $fileName,
            null,
            ['type' => 'storage_error'],
            $previous,
        );
    }

    public static function validationError(string $message, ?int $userId = null): self
    {
        return self::uploadFailed(
            $message,
            $userId,
            null,
            null,
            ['type' => 'validation_error'],
        );
    }

    public static function databaseError(
        string $message,
        ?int $userId = null,
        ?string $fileName = null,
        ?\Throwable $previous = null,
    ): self {
        return self::uploadFailed(
            $message,
            $userId,
            $fileName,
            null,
            ['type' => 'database_error'],
            $previous,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS
    |--------------------------------------------------------------------------
    */

    public function getContext(): array
    {
        return $this->context;
    }

    public function shouldCleanupStorage(): bool
    {
        return $this->storedPath !== null;
    }

    public function getUserMessage(): string
    {
        return $this->getMessage();
    }
}
