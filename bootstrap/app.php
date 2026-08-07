<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([

            // Admin access
            'admin' => \App\Http\Middleware\AdminOnly::class,

            // SaaS subscription gates
            'paid' => \App\Http\Middleware\CheckSubscription::class,
            'active.sub' => \App\Http\Middleware\EnsureActiveSubscription::class,

        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/razorpay',
        ]);

    })

    ->withSchedule(function (Schedule $schedule): void {

        /*
        |--------------------------------------------------------------------------
        | QUEUE WORKER — runs every minute, stops when empty (shared hosting)
        |--------------------------------------------------------------------------
        */

        $schedule->command('queue:work --stop-when-empty --tries=3 --timeout=60')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

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
        | PURGE SOFT-DELETED USERS PAST 30-DAY RETENTION (daily at 02:00)
        |--------------------------------------------------------------------------
        */

        $schedule->command('users:purge')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        /*
        |--------------------------------------------------------------------------
        | DAILY DATABASE BACKUP (03:00 — off-peak, after risk:generate at 08:00
        | and away from queue:work's every-minute cycle)
        |--------------------------------------------------------------------------
        */

        $schedule->command('backup:database')
            ->dailyAt('03:00')
            ->withoutOverlapping();

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

        /*
        |--------------------------------------------------------------------------
        | ORPHANED ZIP TEMP DIRECTORY CLEANUP (hourly)
        |--------------------------------------------------------------------------
        |
        | A queue timeout kills ProcessPortfolioFile's ZIP extraction via
        | SIGKILL, which skips its cleanup finally block — this sweeps up
        | anything left behind.
        |--------------------------------------------------------------------------
        */

        $schedule->command('portfolio:cleanup-temp-dirs')
            ->hourly();

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })

    ->create();
