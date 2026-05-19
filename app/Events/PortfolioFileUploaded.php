<?php

namespace App\Events;

use App\Models\PortfolioFile;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PortfolioFileUploaded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | PORTFOLIO FILE
    |--------------------------------------------------------------------------
    */

    public readonly PortfolioFile $portfolioFile;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        PortfolioFile $portfolioFile
    ) {

        $this->portfolioFile =
            $portfolioFile;
    }
}
