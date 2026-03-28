<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\PageController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Admin\AdminIntakeController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| SERVICES (NEW ADD)
|--------------------------------------------------------------------------
*/
use App\Services\ReportService;
use App\Models\ClientIntake;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES (LANDING + SECTIONS)
|--------------------------------------------------------------------------
| Single-page app → sab sections home pe hi render ho rahe hain
*/
Route::get('/', [PageController::class, 'home'])->name('home');

/*
|--------------------------------------------------------------------------
| TEST ROUTE (DEV ONLY)
|--------------------------------------------------------------------------
*/
Route::get('/test', function () {
    return view('test');
})->name('test');

/*
|--------------------------------------------------------------------------
| TEST REPORT (🔥 NEW - IMPORTANT)
|--------------------------------------------------------------------------
| Weekly report test (manual trigger)
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
| INTAKE (FORM SUBMISSION)
|--------------------------------------------------------------------------
*/
Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])
    ->name('ifa.submit');

/*
|--------------------------------------------------------------------------
| DASHBOARD (AUTH)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| ADMIN PANEL
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
| PROFILE (USER SETTINGS)
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
| AUTH (BREEZE)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
 