<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateRiskScore::class,
        \App\Console\Commands\ExpireSubscriptions::class,
        \App\Console\Commands\SendDailyWhatsApp::class,
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

        /*
        |--------------------------------------------------------------------------
        | DAILY WHATSAPP RISK SIGNAL (4:30 PM IST)
        |--------------------------------------------------------------------------
        |
        | Dispatches SendWhatsAppMessage jobs on the 'whatsapp' queue for every
        | active subscriber who has a phone number and at least one risk score.
        | Runs AFTER the 8 AM email delivery so scores are always fresh.
        |
        */

        $schedule->command('whatsapp:signal')
            ->dailyAt('16:30');

        /*
        |--------------------------------------------------------------------------
        | EXPIRE SUBSCRIPTIONS (runs just after midnight)
        |--------------------------------------------------------------------------
        */

        $schedule->command('subscriptions:expire')
            ->dailyAt('00:05');

        /*
        |--------------------------------------------------------------------------
        |STALE PENDING PAYMENTS
        |--------------------------------------------------------------------------
        */

        $schedule->call(function () {

            \App\Models\Payment::query()

                ->where('status', 'pending')

                ->where(
                    'created_at',
                    '<',
                    now()->subMinutes(30)
                )

                ->update([
                    'status' => 'failed'
                ]);

        })->everyThirtyMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
