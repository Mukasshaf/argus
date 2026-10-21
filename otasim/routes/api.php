<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sim\Http\Controllers\RaahiSimController;
use Sim\Http\Controllers\SaraiSimController;
use Sim\Http\Controllers\SimStateController;

// Raahi XML endpoints
Route::post('/raahi/availability', [RaahiSimController::class, 'handleAvailability']);
Route::post('/raahi/rates', [RaahiSimController::class, 'handleRates']);

// Sarai JSON endpoints
Route::post('/sarai/oauth/token', [SaraiSimController::class, 'issueToken']);
Route::put('/sarai/v1/properties/{property}/rooms/{room}/availability', [SaraiSimController::class, 'updateAvailability']);
Route::put('/sarai/v1/properties/{property}/rates/{rate}/prices', [SaraiSimController::class, 'updateRates']);
Route::get('/sarai/v1/reservations', [SaraiSimController::class, 'getReservations']);

// Channel state endpoint for verify-sync
Route::get('/sim/{channel}/state', [SimStateController::class, 'getState']);
