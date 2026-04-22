<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminAuthController;

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
| Trial / Lead Capture
|--------------------------------------------------------------------------
*/

Route::post('/ifa-submit', [PageController::class, 'submit'])
    ->name('ifa.submit');

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
| Payments
|--------------------------------------------------------------------------
*/

Route::post('/payment/verify', [PaymentController::class, 'verify'])
    ->name('payment.verify');

Route::get('/payment/success', [PaymentController::class, 'success'])
    ->name('payment.success');

Route::post('/upgrade/{plan}', [PaymentController::class, 'upgrade'])
    ->name('upgrade');

/*
|--------------------------------------------------------------------------
| FORTRESS MODE - Hidden Admin Auth
|--------------------------------------------------------------------------
*/

Route::get('/founder-login-x91', [AdminAuthController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/founder-login-x91', [AdminAuthController::class, 'login'])
    ->name('admin.login.post');

Route::post('/founder-logout-x91', [AdminAuthController::class, 'logout'])
    ->name('admin.logout');

/*
|--------------------------------------------------------------------------
| FORTRESS MODE - Protected Admin Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['admin'])->group(function () {

    Route::get('/founder-control-x91', [AdminController::class, 'index'])
        ->name('admin.dashboard');

});

/*
|--------------------------------------------------------------------------
| Protected User Area
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
| Files
|--------------------------------------------------------------------------
*/

Route::get('/file/{id}', [FileController::class, 'view'])
    ->name('file.view');

/*
|--------------------------------------------------------------------------
| Laravel Auth
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';