<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Market risk snapshot — runs daily after NSE market close (3:30 PM IST = 10:00 UTC)
Schedule::command('market-risk:sync')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('market-risk:sync failed');
    });