<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateRiskScore::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        /*
        |--------------------------------------------------------------------------
        | DAILY RISK DELIVERY
        |--------------------------------------------------------------------------
        */

        $schedule->command('risk:generate')
            ->dailyAt('08:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}