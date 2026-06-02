<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([

            // Admin access
            'admin' => \App\Http\Middleware\AdminOnly::class,

            // SaaS subscription gates
            'paid'       => \App\Http\Middleware\CheckSubscription::class,
            'active.sub' => \App\Http\Middleware\EnsureActiveSubscription::class,

        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/razorpay',
        ]);

    })

    ->withSchedule(function (Schedule $schedule): void {

        /*
        |--------------------------------------------------------------------------
        | DAILY RISK SIGNAL DELIVERY
        |--------------------------------------------------------------------------
        */

        $schedule->command('risk:generate')
            ->dailyAt('08:00');

        /*
        |--------------------------------------------------------------------------
        | EXPIRE SUBSCRIPTIONS (just after midnight each day)
        |--------------------------------------------------------------------------
        */

        $schedule->command('subscriptions:expire')
            ->dailyAt('00:05');

        /*
        |--------------------------------------------------------------------------
        | DAILY WHATSAPP RISK SIGNAL (4:30 PM IST)
        |--------------------------------------------------------------------------
        |
        | Sends WhatsApp messages to active subscribers who have a phone number.
        | Reads the most recent risk score (generated at 8 AM) — no recalculation.
        |
        */

        $schedule->command('whatsapp:signal')
            ->dailyAt('16:30')
            ->withoutOverlapping()
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | STALE PENDING PAYMENTS CLEANUP (every 30 minutes)
        |--------------------------------------------------------------------------
        */

        $schedule->call(function (): void {

            \App\Models\Payment::query()
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->update(['status' => 'failed']);

        })->everyThirtyMinutes();

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })

    ->create();
