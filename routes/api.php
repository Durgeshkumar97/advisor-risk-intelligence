<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadController;

Route::post('/trial-start', [LeadController::class, 'store']);