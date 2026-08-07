<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ROUTES — v1
|--------------------------------------------------------------------------
|
| All API routes are versioned under /api/v1/ from the start so that
| future breaking changes can be shipped as /api/v2/ without touching
| existing integrations (WhatsApp webhooks, AI workflow triggers, etc.).
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | TRIAL SIGN-UP
        |----------------------------------------------------------------------
        |
        | Called by external integrations (WhatsApp flows, AI workflows, etc.)
        | to register a lead and activate a 7-day free trial.
        | Idempotent: returns existing trial if phone is already registered.
        |
        | POST /api/v1/trial-start
        |
        */

        Route::post('/trial-start', [LeadController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('trial.start');

    });

/*
|--------------------------------------------------------------------------
| LEGACY ALIAS — remove after confirming no active integrations use it
|--------------------------------------------------------------------------
|
| The old /api/trial-start URL predates versioning.
| 307 preserves the POST body so the LeadController receives the same data.
|
*/

Route::post('/trial-start', function (\Illuminate\Http\Request $request) {

    return redirect()->action(
        [LeadController::class, 'store'],
        [],
        307  // Temporary Redirect — preserves POST method + body
    );
});
