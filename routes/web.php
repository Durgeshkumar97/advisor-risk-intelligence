<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminController;

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');

Route::view('/terms', 'terms')->name('terms');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/refund', 'refund')->name('refund');

/*
|--------------------------------------------------------------------------
| Trial / Intake Forms
|--------------------------------------------------------------------------
*/

Route::post('/ifa-submit', [PageController::class, 'submit'])->name('ifa.submit');

/*
|--------------------------------------------------------------------------
| Pricing / Checkout
|--------------------------------------------------------------------------
*/

Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

Route::post('/checkout/process', [CheckoutController::class, 'process'])
    ->name('checkout.process');

/*
|--------------------------------------------------------------------------
| Payment System
|--------------------------------------------------------------------------
*/

Route::post('/payment/verify', [PaymentController::class, 'verify'])
    ->name('payment.verify');

Route::get('/payment/success', [PaymentController::class, 'success'])
    ->name('payment.success');

/*
|--------------------------------------------------------------------------
| Upgrade Plans
|--------------------------------------------------------------------------
*/

Route::post('/upgrade/{plan}', [PaymentController::class, 'upgrade'])
    ->name('upgrade');

/*
|--------------------------------------------------------------------------
| File Access
|--------------------------------------------------------------------------
*/

Route::get('/file/{id}', [FileController::class, 'view'])
    ->name('file.view');

/*
|--------------------------------------------------------------------------
| Admin Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/admin', [AdminController::class, 'index'])
        ->name('admin.dashboard');

});

/*
|--------------------------------------------------------------------------
| Authenticated User Area
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Laravel Auth Routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';