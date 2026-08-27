<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Market risk snapshot — runs daily after NSE market close (3:30 PM IST = 10:00 UTC)
// 5-minute mutex rather than withoutOverlapping()'s 1440-minute default: with
// runInBackground(), a SIGKILLed run never reaches schedule:finish to release
// the lock, which would suppress the next day's sync too. This command reads a
// CSV and performs one upsert, so it never legitimately runs that long.
Schedule::command('market-risk:sync')
    ->dailyAt('10:00')
    ->withoutOverlapping(5)
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('market-risk:sync failed');
    });