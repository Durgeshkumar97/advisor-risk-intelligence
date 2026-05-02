<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

// Landing
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Pricing page
Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

// Checkout
Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

// Payment success page (UI only)
Route::get('/payment/success', [CheckoutController::class, 'success'])
    ->name('payment.success');


/*
|--------------------------------------------------------------------------
| AUTH ROUTES (IMPORTANT)
|--------------------------------------------------------------------------
*/

// If you are using Laravel UI / Breeze / Jetstream
Auth::routes();

/*
If you are NOT using Laravel auth scaffolding,
then create your own routes like:

Route::get('/login', fn() => view('auth.login'))->name('login');
Route::get('/register', fn() => view('auth.register'))->name('register');
*/


/*
|--------------------------------------------------------------------------
| PAYMENT ROUTES (CRITICAL)
|--------------------------------------------------------------------------
*/

// Create Razorpay order
Route::post('/payment/create', [PaymentController::class, 'create'])
    ->name('payment.create')
    ->middleware('throttle:20,1');

// Verify payment (UX only)
Route::post('/payment/verify', [PaymentController::class, 'verify'])
    ->name('payment.verify')
    ->middleware('throttle:10,1');


/*
|--------------------------------------------------------------------------
| WEBHOOK (SERVER → SERVER)
|--------------------------------------------------------------------------
*/

// ⚠️ NO auth middleware here
Route::post('/webhook/razorpay', [WebhookController::class, 'handle'])
    ->name('webhook.razorpay');


/*
|--------------------------------------------------------------------------
| AUTH REQUIRED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

});


/*
|--------------------------------------------------------------------------
| PAID USER ROUTES (SaaS CORE)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'paid'])->group(function () {

    Route::get('/reports', function () {
        return view('reports');
    })->name('reports');

    Route::get('/analytics', function () {
        return view('analytics');
    })->name('analytics');

});


/*
|--------------------------------------------------------------------------
| FALLBACK (OPTIONAL BUT IMPORTANT)
|--------------------------------------------------------------------------
*/

Route::fallback(function () {
    return redirect('/');
});