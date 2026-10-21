<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Dto\ReservationEvent;
use App\Enums\IngestResult;
use App\Services\RealtimeBookingService;
use App\Services\ReservationIngestService;
use Illuminate\Console\Command;

class RunRaceWorker extends Command
{
    protected $signature = 'staybridge:race-worker 
        {scenario : race1, race2, race3, race4, race5} 
        {barrier : Start timestamp with microtime} 
        {worker_id : Integer worker id} 
        {--external_id= : Custom external reservation id} 
        {--version=1 : External event version} 
        {--type=upsert : upsert or cancel} 
        {--room=STD : External room code} 
        {--rate=EP : External rate code} 
        {--check_in=2026-12-01 : Check-in date} 
        {--check_out=2026-12-02 : Check-out date} 
        {--nc= : Negative control toggle (nc1, nc2, nc3)}';

    protected $description = 'Worker process for concurrent race barrier testing';

    public function handle(
        RealtimeBookingService $bookingService,
        ReservationIngestService $ingestService
    ): int {
        $scenario = (string) $this->argument('scenario');
        $barrier = (float) $this->argument('barrier');
        $workerId = (int) $this->argument('worker_id');
        $extId = (string) ($this->option('external_id') ?: "RACE-{$scenario}-{$workerId}");
        $version = (int) $this->option('version');
        $type = (string) $this->option('type');
        $room = (string) $this->option('room');
        $rate = (string) $this->option('rate');
        $checkIn = (string) $this->option('check_in');
        $checkOut = (string) $this->option('check_out');
        $nc = (string) $this->option('nc');

        // Apply negative control flags if specified
        if ($nc === 'nc1') {
            RealtimeBookingService::$useUnguardedUpdate = true;
        } elseif ($nc === 'nc2') {
            ReservationIngestService::$skipClaimAndLock = true;
        } elseif ($nc === 'nc3') {
            ReservationIngestService::$useReadModifyWrite = true;
        }

        // Wait at barrier until start_at microtime for true simultaneous concurrency
        $now = microtime(true);
        if ($now < $barrier) {
            $diffMicros = (int) (($barrier - $now) * 1e6);
            if ($diffMicros > 0) {
                usleep($diffMicros);
            }
        }

        if ($scenario === 'race1') {
            // Request mode: atomic guarded decrement
            $evt = new ReservationEvent(
                channelId: 2, // Sarai
                externalId: $extId,
                externalVersion: $version,
                type: $type,
                roomCode: $room,
                rateCode: $rate,
                checkIn: $checkIn,
                checkOut: $checkOut,
                guestName: "Race1 Worker {$workerId}",
                adults: 1,
                totalPrice: 3500,
                currency: 'INR',
                externalEventId: "EVT-{$scenario}-{$workerId}",
            );
            $res = $bookingService->book($evt);
            $this->line("RESULT:{$res['status']}");
            return $res['status'] === 201 ? 0 : 1;
        }

        // Scenarios race2, race3, race4, race5: Notification mode via IngestService
        $channelId = 1; // Raahi
        $evt = new ReservationEvent(
            channelId: $channelId,
            externalId: $extId,
            externalVersion: $version,
            type: $type,
            roomCode: $room,
            rateCode: $rate,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: "Worker {$workerId}",
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: "EVT-{$scenario}-{$workerId}-v{$version}",
        );

        $res = $ingestService->apply($evt);
        $this->line("RESULT:{$res->value}");
        return $res === IngestResult::Applied ? 0 : 2;
    }
}
