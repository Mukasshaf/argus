<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Sim\Http\Controllers\RaahiSimController;
use Sim\Http\Controllers\SaraiSimController;
use Sim\Http\Controllers\SimStateController;

Route::get('/', function () {
    $configs = DB::table('sim_config')->get()->keyBy('channel_code');
    $stateCount = DB::table('sim_state')->count();
    return view('control_panel', [
        'configs' => $configs,
        'stateCount' => $stateCount,
    ]);
});

Route::post('/sim/config', function () {
    $channel = request('channel_code');
    $mode = request('mode', 'ok');
    $latency = (int) request('latency_ms', 0);

    DB::table('sim_config')->updateOrInsert(
        ['channel_code' => $channel],
        ['mode' => $mode, 'latency_ms' => $latency, 'updated_at' => now()]
    );

    return back()->with('success', "Fault mode for {$channel} set to {$mode}.");
});

// Simultaneous race trigger: "book the last room on Raahi and Sarai simultaneously" (Section 8)
Route::post('/sim/race/simultaneous', function (RaahiSimController $raahi, SaraiSimController $sarai) {
    $today = now()->toDateString();
    $tomorrow = now()->addDay()->toDateString();

    $externalIdRaahi = 'RACE-RAA-' . time();
    $externalIdSarai = 'RACE-SAR-' . time();

    // Trigger both in parallel / sequence
    $raahiRes = app(RaahiSimController::class)->sendWebhook(request()->merge([
        'external_id' => $externalIdRaahi,
        'room_code' => 'STD',
        'rate_code' => 'EP',
        'check_in' => $today,
        'check_out' => $tomorrow,
    ]));

    $saraiRes = app(SaraiSimController::class)->requestBooking(request()->merge([
        'external_id' => $externalIdSarai,
        'room_code' => 'STD',
        'rate_code' => 'EP',
        'check_in' => $today,
        'check_out' => $tomorrow,
    ]));

    return back()->with('success', "Fired simultaneous bookings! Raahi Status: {$raahiRes->getStatusCode()}, Sarai Status: {$saraiRes->getStatusCode()}");
});

Route::post('/sim/raahi/webhook', [RaahiSimController::class, 'sendWebhook']);
Route::post('/sim/sarai/booking', [SaraiSimController::class, 'requestBooking']);
Route::get('/sim/{channel}/state', [SimStateController::class, 'getState']);
