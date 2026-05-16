<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

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
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIntakeController;

/*
|--------------------------------------------------------------------------
| SERVICES & MODELS
|--------------------------------------------------------------------------
*/

use App\Services\ReportService;
use App\Models\ClientIntake;

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

/*--------------------------------------------------------------------------
| PORTFOLIO UPLOAD
|-------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get(
        '/portfolio/upload',
        [PortfolioUploadController::class, 'index']
    )->name('portfolio.upload');
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

    $user->update([
        'login_token' => null,
        'last_login_at' => now(),
    ]);

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
        ]);

        /*
        |--------------------------------------------------------------------------
        | GET AUTH USER
        |--------------------------------------------------------------------------
        */

        $user = \Illuminate\Support\Facades\Auth::user();

        /*
        |--------------------------------------------------------------------------
        | UPDATE USER SETTINGS
        |--------------------------------------------------------------------------
        */

        $user->update([
            'access_type' => $request->access_type,
            'onboarding_completed' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT TO DASHBOARD
        |--------------------------------------------------------------------------
        */

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

    Route::post('/upgrade', [SubscriptionController::class, 'upgrade'])
        ->name('upgrade');

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
| ADMIN PANEL
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/intakes', [AdminIntakeController::class, 'index'])
            ->name('intakes.index');

        Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])
            ->name('intakes.show');
    });

/*
|--------------------------------------------------------------------------
| TEST ROUTES (DEV ONLY)
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])->group(function () {

    Route::get('/checkout/test', function () {

        if (!app()->environment('local')) {
            abort(404);
        }

        $plan = \App\Models\Plan::where(
            'slug',
            'test-plan'
        )->firstOrFail();

        return view('checkout', compact('plan'));
    });
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
