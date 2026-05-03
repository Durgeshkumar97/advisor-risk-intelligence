<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\User\DashboardController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/pricing', fn () => view('pricing'))->name('pricing');

Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

Route::get('/payment/success', [CheckoutController::class, 'success'])
    ->name('payment.success');

/*
|--------------------------------------------------------------------------
| USER AUTH (CUSTOMERS)
|--------------------------------------------------------------------------
*/

Route::controller(AuthController::class)->group(function () {

    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->name('login.post');

    Route::get('/register', 'showRegister')->name('register');
    Route::post('/register', 'register')->name('register.post');

    Route::post('/logout', 'logout')->name('logout');

});


/*
|--------------------------------------------------------------------------
| ADMIN AUTH (FOUNDER ONLY)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::controller(AdminAuthController::class)->group(function () {

            Route::get('/login', 'showLogin')->name('login');
            Route::post('/login', 'login')->name('login.post');
            Route::post('/logout', 'logout')->name('logout');

        });

        /*
        |--------------------------------------------------------------------------
        | ADMIN PROTECTED
        |--------------------------------------------------------------------------
        */

        Route::middleware(['auth:admin'])->group(function () {

            Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                ->name('dashboard');

        });

    });


/*
|--------------------------------------------------------------------------
| PAYMENT ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('payment')->name('payment.')->group(function () {

    Route::post('/create', [PaymentController::class, 'create'])
        ->name('create')
        ->middleware('throttle:20,1');

    Route::post('/verify', [PaymentController::class, 'verify'])
        ->name('verify')
        ->middleware('throttle:10,1');

});


/*
|--------------------------------------------------------------------------
| WEBHOOK (SERVER TO SERVER)
|--------------------------------------------------------------------------
*/

Route::post('/webhook/razorpay', [WebhookController::class, 'handle'])
    ->name('webhook.razorpay');


/*
|--------------------------------------------------------------------------
| USER DASHBOARD
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

});


/*
|--------------------------------------------------------------------------
| PAID USER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'paid'])->group(function () {

    Route::get('/reports', fn () => view('reports'))->name('reports');

    Route::get('/analytics', fn () => view('analytics'))->name('analytics');

});


/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/

Route::fallback(fn () => redirect('/'));