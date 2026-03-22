<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Admin\AdminIntakeController; 

Route::get('/', [PageController::class, 'home']); 
Route::get('/dashboard', [DashboardController::class, 'index']); 
Route::get('/service', [PageController::class, 'service']);
Route::get('/how-it-works', [PageController::class, 'how']);
Route::get('/sample-report', [PageController::class, 'sample']);
Route::get('/pricing', [PageController::class, 'pricing']);
Route::get('/about', [PageController::class, 'about']);
Route::get('/legal', [PageController::class, 'legal']);
Route::get('/contact', [PageController::class, 'contact']);

Route::get('/intake', [PageController::class, 'intake']);
Route::post('/intake-submit', [IntakeController::class, 'submit'])->name('intake.submit');
Route::post('/ifa-submit', [IntakeController::class, 'ifaSubmit'])->name('ifa.submit');
Route::get('/market-returns', [MarketController::class, 'returns'])->name('market.returns');
Route::get('/investor-profile', function () {
    return view('archetype');
});
 
Route::prefix('admin')->group(function () { 
    Route::get('/intakes', [AdminIntakeController::class, 'index'])
        ->name('admin.intakes.index');

    Route::get('/intakes/{id}', [AdminIntakeController::class, 'show'])
        ->name('admin.intakes.show');
}); 

