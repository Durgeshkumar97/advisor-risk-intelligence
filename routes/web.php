<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Admin\AdminIntakeController;

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES
|--------------------------------------------------------------------------
*/
Route::controller(PageController::class)->group(function () {
    Route::get('/',              'home')->name('home');
    Route::get('/service',       'service')->name('service');
    Route::get('/how-it-works',  'how')->name('how');
    Route::get('/sample-report', 'sample')->name('sample');
    Route::get('/pricing',       'pricing')->name('pricing');
    Route::get('/about',         'about')->name('about');
    Route::get('/legal',         'legal')->name('legal');
    Route::get('/contact',       'contact')->name('contact');
});

/*
|--------------------------------------------------------------------------
| INTAKE & LEAD CAPTURE (RATE LIMITED)
|--------------------------------------------------------------------------
*/
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])
        ->name('ifa.submit');

    Route::post('/intake-submit', [IntakeController::class, 'submit'])
        ->name('intake.submit');
});

// Legacy intake page
Route::get('/intake', [PageController::class, 'intake'])->name('intake');

/*
|--------------------------------------------------------------------------
| TOOLS
|--------------------------------------------------------------------------
*/
Route::prefix('tools')->group(function () {
    Route::get('/market-returns', [MarketController::class, 'returns'])
        ->name('market.returns');

    Route::get('/investor-profile', fn () => view('archetype'))
        ->name('investor.profile');
});

/*
|--------------------------------------------------------------------------
| USER DASHBOARD (PROTECTED)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| ADMIN PANEL (STRICT CONTROL)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/intakes', [AdminIntakeController::class, 'index'])
            ->name('intakes.index');

        Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])
            ->name('intakes.show');
    });