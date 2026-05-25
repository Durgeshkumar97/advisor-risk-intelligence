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

use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\PortfolioUploadController;
use App\Http\Controllers\PortfolioController;
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

Route::post('/payment/verify', [PaymentController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('payment.verify');

Route::get('/payment/success', [CheckoutController::class, 'success'])
    ->name('payment.success');

Route::post('/payment/failure', [PaymentController::class, 'failure'])
    ->name('payment.failure');

/*
|--------------------------------------------------------------------------
| PORTFOLIO MANAGEMENT (create / rename / delete portfolios)
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

/*------------PORTFOLIO UPLOAD ----------------------------------------*/
Route::middleware(['auth'])->group(function () {

    Route::get(
        '/portfolio/upload',
        [PortfolioUploadController::class, 'index']
    )->name('portfolio.upload');

    /*
    |--------------------------------------------------------------------------
    | PORTFOLIO FILE SUBMIT
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/portfolio/upload',
        [PortfolioUploadController::class, 'store']
    )->name('portfolio.upload.store');

    Route::delete(
        '/portfolio/file/{id}',
        [PortfolioUploadController::class, 'destroy']
    )->name('portfolio.file.destroy');
});

/*
|--------------------------------------------------------------------------
| WEBHOOK
|--------------------------------------------------------------------------
*/

Route::post('/webhook/razorpay', [WebhookController::class, 'handle'])
    ->name('webhook.razorpay');

/*
|--------------------------------------------------------------------------
| AUTO LOGIN
|--------------------------------------------------------------------------
*/

Route::get('/auto-login/{token}', function ($token) {

    $user = \App\Models\User::where('login_token', $token)->first();

    if (!$user) {
        return redirect()->route('login');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN USER
    |--------------------------------------------------------------------------
    */

    \Illuminate\Support\Facades\Auth::login($user);

    /*
    |--------------------------------------------------------------------------
    | UPDATE LOGIN STATE
    |--------------------------------------------------------------------------
    */

    $user->forceFill([
        'login_token' => null,
        'last_login_at' => now(),
    ])->save();

    /*
    |--------------------------------------------------------------------------
    | ONBOARDING CHECK
    |--------------------------------------------------------------------------
    */

    if (!$user->onboarding_completed) {
        return redirect()->route('onboarding');
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL REDIRECT
    |--------------------------------------------------------------------------
    */

    return redirect()->route('dashboard');
})->name('auto.login');

/*
|--------------------------------------------------------------------------
| ONBOARDING
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | SHOW ONBOARDING
    |--------------------------------------------------------------------------
    */

    Route::get('/onboarding', function () {

        return view('onboarding');
    })->name('onboarding');

    /*
    |--------------------------------------------------------------------------
    | SAVE ONBOARDING
    |--------------------------------------------------------------------------
    */

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

    Route::get('/file/{id}', [FileController::class, 'view'])
        ->name('file.view');
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
| ADMIN AUTH — public (login / logout, no middleware)
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
| ADMIN PANEL — protected (admin middleware = auth + is_admin check)
|--------------------------------------------------------------------------
*/

Route::middleware(['admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

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
| FALLBACK
|--------------------------------------------------------------------------
*/

Route::fallback(function () {

    return redirect()->route('home');
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
