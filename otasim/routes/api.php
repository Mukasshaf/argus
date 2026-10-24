<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sim\Http\Controllers\RaahiSimController;
use Sim\Http\Controllers\SaraiSimController;
use Sim\Http\Controllers\SimStateController;

// Raahi XML endpoints (handles standard, /api prefix, and /raahi base_url prefix)
Route::post('/raahi/availability', [RaahiSimController::class, 'handleAvailability']);
Route::post('/raahi/rates', [RaahiSimController::class, 'handleRates']);
Route::post('/raahi/raahi/availability', [RaahiSimController::class, 'handleAvailability']);
Route::post('/raahi/raahi/rates', [RaahiSimController::class, 'handleRates']);
Route::post('/api/raahi/availability', [RaahiSimController::class, 'handleAvailability']);
Route::post('/api/raahi/rates', [RaahiSimController::class, 'handleRates']);

// Sarai JSON endpoints (handles standard, /api prefix, and /sarai base_url prefix)
Route::post('/sarai/oauth/token', [SaraiSimController::class, 'issueToken']);
Route::post('/sarai/sarai/oauth/token', [SaraiSimController::class, 'issueToken']);
Route::post('/api/sarai/oauth/token', [SaraiSimController::class, 'issueToken']);

Route::put('/sarai/v1/properties/{property}/rooms/{room}/availability', [SaraiSimController::class, 'updateAvailability']);
Route::put('/sarai/sarai/v1/properties/{property}/rooms/{room}/availability', [SaraiSimController::class, 'updateAvailability']);
Route::put('/api/sarai/v1/properties/{property}/rooms/{room}/availability', [SaraiSimController::class, 'updateAvailability']);

Route::put('/sarai/v1/properties/{property}/rates/{rate}/prices', [SaraiSimController::class, 'updateRates']);
Route::put('/sarai/sarai/v1/properties/{property}/rates/{rate}/prices', [SaraiSimController::class, 'updateRates']);
Route::put('/api/sarai/v1/properties/{property}/rates/{rate}/prices', [SaraiSimController::class, 'updateRates']);

Route::get('/sarai/v1/reservations', [SaraiSimController::class, 'getReservations']);
Route::get('/sarai/sarai/v1/reservations', [SaraiSimController::class, 'getReservations']);
Route::get('/api/sarai/v1/reservations', [SaraiSimController::class, 'getReservations']);

// Channel state endpoint for verify-sync
Route::get('/sim/{channel}/state', [SimStateController::class, 'getState']);
Route::get('/api/sim/{channel}/state', [SimStateController::class, 'getState']);
