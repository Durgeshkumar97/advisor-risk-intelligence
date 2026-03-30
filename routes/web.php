<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\PageController; 
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Admin\AdminIntakeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| SERVICES
|--------------------------------------------------------------------------
*/
use App\Services\ReportService;
use App\Models\ClientIntake;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES
|--------------------------------------------------------------------------
*/ 
Route::get('/', [PageController::class, 'home'])->name('home');

/*
|--------------------------------------------------------------------------
| TEST ROUTE
|--------------------------------------------------------------------------
*/
Route::get('/test', function () {
    return view('test');
})->name('test');

/*
|--------------------------------------------------------------------------
| TEST REPORT (DEBUG TOOL)
|--------------------------------------------------------------------------
*/ 
Route::get('/test-report', function () {

    $user = ClientIntake::first();

    if (!$user) {
        return "No users found in database";
    }

    $service = new ReportService(); 
    $report = $service->generate($user);

    Mail::raw($report, function ($message) use ($user) {
        $message->to($user->email ?? 'test@example.com')
            ->subject('Test Weekly Report');
    });

    return "Report sent successfully!";
});

/*
|--------------------------------------------------------------------------
| INTAKE
|--------------------------------------------------------------------------
*/
Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])
    ->name('ifa.submit');

/*
|--------------------------------------------------------------------------
| CHECKOUT + PAYMENT FLOW (FINAL)
|--------------------------------------------------------------------------
*/
Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])
    ->name('checkout');

Route::post('/checkout/process', [CheckoutController::class, 'process'])
    ->name('checkout.process');

Route::get('/payment/success', [CheckoutController::class, 'success'])
    ->name('payment.success');

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/intakes', [AdminIntakeController::class, 'index'])
            ->name('intakes.index');

        Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])
            ->name('intakes.show');

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

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/refund', 'legal.refund')->name('refund');

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
 