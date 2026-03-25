<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Admin\AdminIntakeController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/service', [PageController::class, 'service']);
Route::get('/how-it-works', [PageController::class, 'how']);
Route::get('/sample-report', [PageController::class, 'sample']);
Route::get('/pricing', [PageController::class, 'pricing']);
Route::get('/about', [PageController::class, 'about']);
Route::get('/legal', [PageController::class, 'legal']);
Route::get('/contact', [PageController::class, 'contact']);

/*
|--------------------------------------------------------------------------
| INTAKE
|--------------------------------------------------------------------------
*/
Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])->name('ifa.submit');
Route::post('/intake-submit', [IntakeController::class, 'submit']);

/*
|--------------------------------------------------------------------------
| AUTH DASHBOARD (Breeze)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| ADMIN PANEL (IMPORTANT)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/intakes', [AdminIntakeController::class, 'index'])->name('intakes.index');
    Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])->name('intakes.show');
});

/*
|--------------------------------------------------------------------------
| PROFILE (Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';