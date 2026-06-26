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
 | payment/failure is called by Razorpay's client-side JS checkout handler
 | when a payment fails in the modal (network drop, bank decline, etc.).
 | There is NO server-side Razorpay signature on failure events — that only
 | exists on payment.captured webhooks (handled by /webhook/razorpay).
 |
 | Risk: low — failure only marks a pending row as 'failed'; it cannot mark
 | a paid order as failed. Throttle limits abuse / order-id guessing.
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
        return redirect()->route('login')
            ->with('error', 'This login link is invalid or has expired. Please use Forgot Password to access your account.');
    }

    if ($user->login_token_expires_at?->isPast()) {
        $user->forceFill([
            'login_token'            => null,
            'login_token_expires_at' => null,
        ])->save();

        return redirect()->route('login')
            ->with('error', 'This login link is invalid or has expired. Please use Forgot Password to access your account.');
    }

    \Illuminate\Support\Facades\Auth::login($user);

    $user->forceFill([
        'login_token'            => null,
        'login_token_expires_at' => null,
        'last_login_at'          => now(),
    ])->save();

    if (!$user->onboarding_completed) {
        return redirect()->route('onboarding');
    }

    return redirect()->route('dashboard');
})->middleware(['throttle:5,1', 'guest'])->name('auto.login');

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
            'access_type' => 'required|in:email',
            'phone'       => 'nullable|string|min:10|max:20',
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

// Subscription management stays auth-only so expired/trial users can upgrade.
Route::middleware(['auth'])->group(function () {

    Route::get('/subscription', [SubscriptionController::class, 'index'])
        ->name('subscription.index');

    Route::delete('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');
});

// Dashboard and file routes require an active or in-trial subscription.
Route::middleware(['auth', 'paid'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/file/{id}', [FileController::class, 'view'])
        ->name('file.view');

    Route::get('/report/{id}', [FileController::class, 'report'])
        ->name('report.view');

    Route::get('/report/{id}/download', [FileController::class, 'reportDownload'])
        ->name('report.download');

    Route::get('/report/{id}/bundle', [FileController::class, 'bundleDownload'])
        ->name('file.bundle.download');
});

/*
|--------------------------------------------------------------------------
| PORTFOLIO MANAGEMENT — create / rename / delete named portfolios
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'paid'])->group(function () {

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

Route::middleware(['auth', 'paid'])->group(function () {

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
| ADMIN PANEL
|--------------------------------------------------------------------------
*/

/*
|----------------------------------------------------------------------
| ADMIN AUTH — login/logout (no admin middleware; these are the gate)
|----------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/login', [\App\Http\Controllers\Admin\AdminAuthController::class, 'showLogin'])
        ->middleware('guest')
        ->name('login');

    Route::post('/login', [\App\Http\Controllers\Admin\AdminAuthController::class, 'login'])
        ->middleware(['guest', 'throttle:10,1'])
        ->name('login.submit');

    Route::post('/logout', [\App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
});

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
|
| Returns a proper HTTP 404 (not a 302 redirect to home).
| The custom errors/404.blade.php view is used so the page stays
| on-brand. Search engines receive the correct status code and
| do not index dead URLs as home.
|
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
