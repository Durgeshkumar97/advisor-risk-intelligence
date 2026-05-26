<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\PageController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\PortfolioController;

use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\PortfolioUploadController;

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIntakeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminPaymentController;

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])
    ->name('home');

Route::view('/pricing', 'pricing')
    ->name('pricing');

/*
|--------------------------------------------------------------------------
| LEGAL PAGES
|--------------------------------------------------------------------------
*/

Route::view('/terms', 'legal.terms')
    ->name('terms');

Route::view('/privacy', 'legal.privacy')
    ->name('privacy');

Route::view('/refund', 'legal.refund')
    ->name('refund');

/*
|--------------------------------------------------------------------------
| IFA LEAD SUBMISSION
|--------------------------------------------------------------------------
*/

Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])
    ->middleware('throttle:20,1')
    ->name('ifa.submit');

/*
|--------------------------------------------------------------------------
| CHECKOUT
|--------------------------------------------------------------------------
*/

Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->whereIn('plan', ['starter', 'pro', 'team'])
    ->name('checkout.show');

/*
|--------------------------------------------------------------------------
| PAYMENT ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/payment/create', [PaymentController::class, 'create'])
    ->middleware('throttle:20,1')
    ->name('payment.create');

Route::post('/payment/verify', [CheckoutController::class, 'success'])
    ->middleware('throttle:10,1')
    ->name('payment.verify');

Route::view('/payment/success', 'payment-success')
    ->name('payment.success');

/*
 | payment/failure is fired by Razorpay client-side JS — no server
 | signature on failure events. Throttle prevents order-id bruteforcing.
 | Only pending payments can be flipped to failed (guard in controller).
 */
Route::post('/payment/failure', [PaymentController::class, 'failure'])
    ->middleware('throttle:15,1')
    ->name('payment.failure');

/*
|--------------------------------------------------------------------------
| WEBHOOK — Razorpay server-to-server (HMAC verified inside controller)
|--------------------------------------------------------------------------
*/

Route::post('/webhook/razorpay', [WebhookController::class, 'handle'])
    ->name('webhook.razorpay');

/*
|--------------------------------------------------------------------------
| AUTO LOGIN — magic link sent from admin panel
|--------------------------------------------------------------------------
*/

Route::get('/auto-login/{token}', function ($token) {

    $user = \App\Models\User::where('login_token', $token)->first();

    if (!$user) {
        return redirect()->route('login');
    }

    \Illuminate\Support\Facades\Auth::login($user);

    $user->forceFill([
        'login_token'   => null,
        'last_login_at' => now(),
    ])->save();

    if (!$user->onboarding_completed) {
        return redirect()->route('onboarding');
    }

    return redirect()->route('dashboard');

})->name('auto.login');

/*
|--------------------------------------------------------------------------
| ONBOARDING
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/onboarding', function () {
        return view('onboarding');
    })->name('onboarding');

    Route::post('/onboarding', function (\Illuminate\Http\Request $request) {

        $request->validate([
            'access_type' => 'required|in:email,whatsapp',
            'phone'       => 'nullable|string|min:10|max:20|required_if:access_type,whatsapp',
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        $updates = [
            'login_method'         => $request->access_type,
            'onboarding_completed' => true,
        ];

        if ($request->filled('phone')) {
            $updates['phone'] = $request->phone;
        }

        $user->forceFill($updates)->save();

        return redirect()->route('dashboard');

    })->name('onboarding.store');
});

/*
|--------------------------------------------------------------------------
| USER DASHBOARD
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTION MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/subscription', [SubscriptionController::class, 'index'])
        ->name('subscription.index');

    Route::delete('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');

    /*
    |--------------------------------------------------------------------------
    | FILE VIEW / DOWNLOAD
    |--------------------------------------------------------------------------
    */

    Route::get('/file/{id}', [FileController::class, 'view'])
        ->name('file.view');
});

/*
|--------------------------------------------------------------------------
| PORTFOLIO MANAGEMENT — create / rename / delete named portfolios
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/portfolios', [PortfolioController::class, 'index'])
        ->name('portfolio.manage');

    Route::post('/portfolios', [PortfolioController::class, 'store'])
        ->name('portfolio.store');

    Route::patch('/portfolios/{id}', [PortfolioController::class, 'update'])
        ->name('portfolio.update');

    Route::delete('/portfolios/{id}', [PortfolioController::class, 'destroy'])
        ->name('portfolio.destroy');
});

/*
|--------------------------------------------------------------------------
| PORTFOLIO UPLOAD
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/portfolio/upload', [PortfolioUploadController::class, 'index'])
        ->name('portfolio.upload');

    Route::post('/portfolio/upload', [PortfolioUploadController::class, 'store'])
        ->name('portfolio.upload.store');

    Route::delete('/portfolio/file/{id}', [PortfolioUploadController::class, 'destroy'])
        ->name('portfolio.file.destroy');
});

/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| ADMIN AUTH — public (no middleware)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AdminAuthController::class, 'login'])
        ->name('login.post');

    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| ADMIN PANEL — protected by AdminOnly middleware (auth + is_admin check)
|--------------------------------------------------------------------------
*/

Route::middleware(['admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        /*
        |----------------------------------------------------------------------
        | INTAKES / LEADS
        |----------------------------------------------------------------------
        */

        Route::get('/intakes', [AdminIntakeController::class, 'index'])
            ->name('intakes.index');

        Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])
            ->name('intakes.show');

        Route::post('/intakes/{id}/status', [AdminIntakeController::class, 'updateStatus'])
            ->name('intakes.status');

        /*
        |----------------------------------------------------------------------
        | USERS
        |----------------------------------------------------------------------
        */

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/{id}', [AdminUserController::class, 'show'])
            ->name('users.show');

        Route::post('/users/{id}/login-link', [AdminUserController::class, 'sendLoginLink'])
            ->name('users.login-link');

        /*
        |----------------------------------------------------------------------
        | PAYMENTS
        |----------------------------------------------------------------------
        */

        Route::get('/payments', [AdminPaymentController::class, 'index'])
            ->name('payments.index');
    });

/*
|--------------------------------------------------------------------------
| FALLBACK — proper 404 (not a 302 redirect to home)
|--------------------------------------------------------------------------
*/

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (Breeze)
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
