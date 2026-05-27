<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Carries the raw input needed to trigger a portfolio upload.
 * Passed from the controller to PortfolioUploadService::handleUpload().
 */
final class UploadedPortfolioDTO
{
    public function __construct(
        public readonly int          $userId,
        public readonly UploadedFile $file,
        public readonly ?int         $portfolioId = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | FACTORY — build from validated request data
    |--------------------------------------------------------------------------
    */

    public static function fromRequest(
        int          $userId,
        UploadedFile $file,
        ?int         $portfolioId = null,
    ): self {
        return new self(
            userId:      $userId,
            file:        $file,
            portfolioId: $portfolioId,
        );
    }
}
