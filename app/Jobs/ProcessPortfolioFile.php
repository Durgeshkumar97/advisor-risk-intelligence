<?php

namespace App\Jobs;

use App\Models\PortfolioFile;
use App\Models\RiskScore;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
    | JOB SETTINGS
    |--------------------------------------------------------------------------
    */

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    /*
    |--------------------------------------------------------------------------
    | FILE
    |--------------------------------------------------------------------------
    */

    public PortfolioFile $portfolioFile;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(PortfolioFile $portfolioFile)
    {
        $this->portfolioFile = $portfolioFile;
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        DB::beginTransaction();

        try {

            /*
            |------------------------------------------------------------------
            | REFRESH MODEL
            */
            $this->portfolioFile->refresh();
        } catch (\Exception $e) {
            Log::error('Error processing portfolio file: ' . $e->getMessage());
            DB::rollBack();
            throw $e;
        }
    }
}
