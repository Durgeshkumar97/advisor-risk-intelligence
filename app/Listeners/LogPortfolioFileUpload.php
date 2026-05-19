<?php

namespace App\Listeners;

use App\Events\PortfolioFileUploaded;

use Illuminate\Support\Facades\Log;

class LogPortfolioFileUpload
{
    /*
    |--------------------------------------------------------------------------
    | HANDLE EVENT
    |--------------------------------------------------------------------------
    */

    public function handle(
        PortfolioFileUploaded $event
    ): void {

        $file = $event->portfolioFile;

        /*
        |--------------------------------------------------------------------------
        | STRUCTURED LOGGING
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Portfolio file uploaded successfully.',
            [

                /*
                |--------------------------------------------------------------------------
                | FILE INFO
                |--------------------------------------------------------------------------
                */

                'portfolio_file_id' =>
                $file->id,

                'user_id' =>
                $file->user_id,

                'portfolio_id' =>
                $file->portfolio_id,

                /*
                |--------------------------------------------------------------------------
                | FILE DETAILS
                |--------------------------------------------------------------------------
                */

                'original_name' =>
                $file->original_name,

                'stored_name' =>
                $file->stored_name,

                'path' =>
                $file->path,

                'mime_type' =>
                $file->mime_type,

                'file_size' =>
                $file->file_size,

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                'status' =>
                $file->status,

                /*
                |--------------------------------------------------------------------------
                | TIMESTAMP
                |--------------------------------------------------------------------------
                */

                'uploaded_at' =>
                now()->toIso8601String(),
            ]
        );
    }
}
