<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\PageController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminAuthController;

/*
|--------------------------------------------------------------------------
| HONEYPOT ADMIN TRAP
|--------------------------------------------------------------------------
*/

Route::get('/admin', function () {

    DB::table('security_events')->insert([
        'type'       => 'honeypot_hit',
        'ip'         => request()->ip(),
        'agent'      => request()->userAgent(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Log::warning('HONEYPOT HIT', [
        'ip' => request()->ip(),
        'agent' => request()->userAgent(),
    ]);

    abort(404);
});

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');

Route::view('/terms', 'terms')->name('terms');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/refund', 'refund')->name('refund');

/*
|--------------------------------------------------------------------------
| LEAD CAPTURE
|--------------------------------------------------------------------------
*/

Route::post('/ifa-submit', [PageController::class, 'submit'])
    ->name('ifa.submit');

/*
|--------------------------------------------------------------------------
| CHECKOUT
|--------------------------------------------------------------------------
*/

Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

/*
|--------------------------------------------------------------------------
| PAYMENTS (RAZORPAY)
|--------------------------------------------------------------------------
*/

Route::post('/payment/create', [PaymentController::class, 'create'])
    ->name('payment.create');

Route::post('/payment/verify', [PaymentController::class, 'verify'])
    ->name('payment.verify');

Route::get('/payment/success', [PaymentController::class, 'success'])
    ->name('payment.success');

/*
|--------------------------------------------------------------------------
| HIDDEN ADMIN AUTH
|--------------------------------------------------------------------------
*/

Route::get('/founder-login-x91', [AdminAuthController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/founder-login-x91', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('admin.login.post');

Route::post('/founder-logout-x91', [AdminAuthController::class, 'logout'])
    ->name('admin.logout');

/*
|--------------------------------------------------------------------------
| ADMIN PANEL
|--------------------------------------------------------------------------
*/

Route::middleware(['admin'])->group(function () {

    Route::get('/founder-control-x91', [AdminController::class, 'index'])
        ->name('admin.dashboard');
});

/*
|--------------------------------------------------------------------------
| USER PANEL
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
| FILE VIEWER
|--------------------------------------------------------------------------
*/

Route::get('/file/{id}', [FileController::class, 'view'])
    ->name('file.view');

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';