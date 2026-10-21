<?php

declare(strict_types=1);

use App\Http\Controllers\Inbound\RaahiWebhookController;
use App\Http\Controllers\Inbound\SaraiBookingController;
use Illuminate\Support\Facades\Route;

// Inbound webhook routes
Route::post('/inbound/raahi/webhook', [RaahiWebhookController::class, 'handle'])
    ->middleware('channel.signature')
    ->name('inbound.raahi.webhook');

// Sarai real-time synchronous booking endpoint
Route::post('/inbound/sarai/booking-requests', [SaraiBookingController::class, 'handle'])
    ->name('inbound.sarai.booking');
