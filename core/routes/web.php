<?php

declare(strict_types=1);

use App\Http\Controllers\CompareController;
use App\Http\Controllers\Extranet\AlertController;
use App\Http\Controllers\Extranet\AuthController;
use App\Http\Controllers\Extranet\CalendarController;
use App\Http\Controllers\Extranet\ChannelController;
use App\Http\Controllers\Extranet\ReservationController;
use App\Http\Controllers\Extranet\SyncLogController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/compare/{hotel}', [CompareController::class, 'index'])->name('compare');


// Extranet Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', fn() => redirect()->route('calendar'));

    // Calendar
    Route::get('/extranet/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::patch('/extranet/calendar/cell', [CalendarController::class, 'updateCell'])->name('calendar.cell');
    Route::post('/extranet/calendar/bulk', [CalendarController::class, 'bulkEdit'])->name('calendar.bulk');
    Route::get('/extranet/sync-status', [CalendarController::class, 'syncStatus'])->name('calendar.sync-status');

    // Reservations
    Route::get('/extranet/reservations', [ReservationController::class, 'index'])->name('reservations');
    Route::get('/extranet/reservations/export', [ReservationController::class, 'exportCsv'])->name('reservations.export');
    Route::post('/extranet/reservations/manual', [ReservationController::class, 'manualBooking'])->name('reservations.manual');
    Route::post('/extranet/reservations/reprocess/{id}', [ReservationController::class, 'reprocessEvent'])->name('reservations.reprocess');

    // Channels
    Route::get('/extranet/channels', [ChannelController::class, 'index'])->name('channels');
    Route::post('/extranet/channels/sync-all', [ChannelController::class, 'syncAll'])->name('channels.sync-all');
    Route::post('/extranet/channels/{id}/force-sync', [ChannelController::class, 'forceSync'])->name('channels.force-sync');
    Route::post('/extranet/channels/{id}/toggle-pause', [ChannelController::class, 'togglePause'])->name('channels.toggle-pause');
    Route::post('/extranet/channels/{id}/unblock', [ChannelController::class, 'unblock'])->name('channels.unblock');

    // Sync Logs
    Route::get('/extranet/sync-logs', [SyncLogController::class, 'index'])->name('sync-logs');
    Route::get('/extranet/sync-logs/export', [SyncLogController::class, 'exportCsv'])->name('sync-logs.export');
    Route::post('/extranet/sync-logs/trigger-check', [SyncLogController::class, 'triggerCheck'])->name('sync-logs.trigger-check');
    Route::get('/extranet/sync-logs/{id}', [SyncLogController::class, 'show'])->name('sync-logs.show');

    // Alerts
    Route::get('/extranet/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::post('/extranet/alerts/{id}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::post('/extranet/alerts/{id}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
});
