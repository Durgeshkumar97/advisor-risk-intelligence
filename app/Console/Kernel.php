<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateRiskScore::class,
        \App\Console\Commands\SendDailyWhatsApp::class,
        \App\Console\Commands\ExpireSubscriptions::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:expire')
            ->dailyAt('00:05');

        $schedule->command('risk:generate')
            ->dailyAt('08:00');

        $schedule->command('whatsapp:signal')
            ->dailyAt('16:30')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->call(function () {
            \App\Models\Payment::query()
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->update(['status' => 'failed']);
        })->everyThirtyMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
