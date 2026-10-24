<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Channels\Dto\ReservationEvent;
use App\Enums\AlertType;
use App\Enums\ReservationStatus;
use App\Models\Alert;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationNight;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\ReservationIngestService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RaceTest extends TestCase
{
    use DatabaseTruncation;

    private Hotel $hotel;
    private RoomType $room;
    private RatePlan $plan;
    private Channel $raahi;
    private Channel $sarai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create([
            'name' => 'Lotus Residency',
            'city' => 'Bengaluru',
            'code' => 'LOTUS01',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);

        $this->room = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'code' => 'STD',
            'name' => 'Standard',
            'total_rooms' => 10,
        ]);

        $this->plan = RatePlan::create([
            'room_type_id' => $this->room->id,
            'code' => 'EP',
            'name' => 'EP',
            'base_price' => 3500,
        ]);

        // The channel ids are assigned by the database, so the workers are
        // told which id to book against (see the --channel option) instead of
        // assuming that the first two channels always get ids 1 and 2.
        $this->raahi = Channel::create([
            'code' => 'raahi',
            'name' => 'Raahi Stays',
            'protocol' => 'xml',
            'markup_bps' => 1000,
            'status' => 'active',
        ]);

        $this->sarai = Channel::create([
            'code' => 'sarai',
            'name' => 'Sarai Direct',
            'protocol' => 'json',
            'markup_bps' => 1200,
            'status' => 'active',
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->raahi->id,
            'room_type_id' => $this->room->id,
            'external_room_code' => 'STD',
        ]);
        ChannelRateMapping::create([
            'channel_id' => $this->raahi->id,
            'rate_plan_id' => $this->plan->id,
            'external_rate_code' => 'EP',
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->sarai->id,
            'room_type_id' => $this->room->id,
            'external_room_code' => 'STD',
        ]);
        ChannelRateMapping::create([
            'channel_id' => $this->sarai->id,
            'rate_plan_id' => $this->plan->id,
            'external_rate_code' => 'EP',
        ]);
    }

    /**
     * Helper to run N child worker processes at a microsecond barrier.
     *
     * @param list<array<string>> $workerArgsList
     * @return list<array{exit_code: int, output: string}>
     */
    private function runWorkersConcurrently(array $workerArgsList): array
    {
        $barrier = sprintf('%.4f', microtime(true) + 1.2);
        $phpBinary = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');

        $processes = [];
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default', 'mysql'),
            'DB_HOST' => config('database.connections.mysql.host', '127.0.0.1'),
            'DB_PORT' => (string) config('database.connections.mysql.port', 3306),
            'DB_DATABASE' => config('database.connections.mysql.database', 'argus_test'),
            'DB_USERNAME' => config('database.connections.mysql.username', 'root'),
            'DB_PASSWORD' => config('database.connections.mysql.password', 'root'),
        ];

        foreach ($workerArgsList as $idx => $args) {
            $cmd = array_merge([$phpBinary, $artisan, 'argus:race-worker'], $args);
            $proc = new Process($cmd, base_path(), $env, null, 60.0);
            $proc->start();
            $processes[] = $proc;
        }

        $results = [];
        foreach ($processes as $proc) {
            $proc->wait();
            $results[] = [
                'exit_code' => $proc->getExitCode() ?? -1,
                'output' => trim($proc->getOutput()),
                'error_output' => trim($proc->getErrorOutput()),
            ];
        }

        // A worker that never reached the command (unknown option, boot error,
        // deadlock) prints no result at all. Counting that as a success would
        // let a broken worker pass the race, so it fails the test outright.
        foreach ($results as $idx => $r) {
            $this->assertStringContainsString(
                'RESULT:',
                $r['output'],
                "Worker {$idx} did not report a result (exit code {$r['exit_code']}). "
                ."stdout: {$r['output']} | stderr: {$r['error_output']}"
            );
        }

        return $results;
    }

    /**
     * Race 1: Request mode last room.
     * Capacity 1, 20 distinct requests for the same night.
     * Expect exactly 1 confirmed (201), 19 rejected (409), booked == 1, available == 0.
     */
    public function test_race_1_request_mode_last_room(): void
    {
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-01',
            'capacity' => 1,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);

        $workerCount = 20;
        $barrier = sprintf('%.4f', microtime(true) + 1.5);
        $argsList = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $argsList[] = [
                'race1',
                $barrier,
                (string) $i,
                "--external_id=RACE1-REQ-{$i}",
                '--external_version=1',
                '--check_in=2026-12-01',
                '--check_out=2026-12-02',
                "--channel={$this->sarai->id}",
            ];
        }

        $results = $this->runWorkersConcurrently($argsList);

        $confirmed = 0;
        $rejected = 0;
        foreach ($results as $r) {
            if (str_contains($r['output'], 'RESULT:201')) {
                $confirmed++;
            } elseif (str_contains($r['output'], 'RESULT:409')) {
                $rejected++;
            }
        }

        $this->assertSame(1, $confirmed, "Expected exactly 1 confirmed reservation in request mode.");
        $this->assertSame(19, $rejected, "Expected 19 rejected requests in request mode.");

        $inv = RoomInventory::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->first();

        $this->assertNotNull($inv);
        $this->assertSame(1, $inv->booked);
        $this->assertSame(0, $inv->available);
        $this->assertGreaterThanOrEqual(0, $inv->available, "Request mode must never oversell.");

        $heldCount = ReservationNight::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->count();
        $this->assertSame($inv->booked, $heldCount, "Invariant: booked == count(reservation_nights).");
    }

    /**
     * Race 2: Notification mode last room.
     * Capacity 1, 20 distinct reservations for the same night.
     * Expect all 20 recorded, booked == 20, available == -19, exactly one oversold alert.
     */
    public function test_race_2_notification_mode_last_room(): void
    {
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-01',
            'capacity' => 1,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);

        $workerCount = 20;
        $barrier = sprintf('%.4f', microtime(true) + 1.5);
        $argsList = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $argsList[] = [
                'race2',
                $barrier,
                (string) $i,
                "--external_id=RACE2-NOTIF-{$i}",
                '--external_version=1',
                '--check_in=2026-12-01',
                '--check_out=2026-12-02',
                "--channel={$this->raahi->id}",
            ];
        }

        $results = $this->runWorkersConcurrently($argsList);

        $applied = 0;
        foreach ($results as $r) {
            if (str_contains($r['output'], 'RESULT:applied')) {
                $applied++;
            }
        }

        $this->assertSame(20, $applied, "Expected all 20 notification mode bookings to be applied.");

        $inv = RoomInventory::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->first();

        $this->assertNotNull($inv);
        $this->assertSame(20, $inv->booked);
        $this->assertSame(-19, $inv->available);

        $oversoldAlerts = Alert::where('type', AlertType::Oversold)->count();
        $this->assertSame(1, $oversoldAlerts, "Expected exactly 1 oversold alert for this range.");

        $heldCount = ReservationNight::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->count();
        $this->assertSame($inv->booked, $heldCount, "Invariant: booked == count(reservation_nights).");
    }

    /**
     * Race 3: Duplicate delivery.
     * The same message (same external_id and version) delivered 20 times concurrently.
     * Expect exactly one reservations row, booked equal to 1, 19 Duplicate results.
     */
    public function test_race_3_duplicate_delivery(): void
    {
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-01',
            'capacity' => 1,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);

        $workerCount = 20;
        $barrier = sprintf('%.4f', microtime(true) + 1.5);
        $argsList = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $argsList[] = [
                'race3',
                $barrier,
                (string) $i,
                '--external_id=RACE3-DUP-TARGET',
                '--external_version=1',
                '--check_in=2026-12-01',
                '--check_out=2026-12-02',
                "--channel={$this->raahi->id}",
            ];
        }

        $results = $this->runWorkersConcurrently($argsList);

        $applied = 0;
        $duplicates = 0;
        foreach ($results as $r) {
            if (str_contains($r['output'], 'RESULT:applied')) {
                $applied++;
            } elseif (str_contains($r['output'], 'RESULT:duplicate')) {
                $duplicates++;
            }
        }

        $this->assertSame(1, $applied, "Expected exactly 1 applied result for duplicate event.");
        $this->assertSame(19, $duplicates, "Expected 19 duplicate results.");

        $resCount = Reservation::where('channel_id', $this->raahi->id)
            ->where('external_id', 'RACE3-DUP-TARGET')
            ->count();
        $this->assertSame(1, $resCount);

        $inv = RoomInventory::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->first();
        $this->assertSame(1, $inv->booked);

        $heldCount = ReservationNight::where('room_type_id', $this->room->id)
            ->where('stay_date', '2026-12-01')
            ->count();
        $this->assertSame($inv->booked, $heldCount, "Invariant: booked == count(reservation_nights).");
    }

    /**
     * Race 4: Concurrent modify (version 2) and cancel (version 3) of one reservation.
     * Expect final state to equal version 3 outcome with a consistent ledger.
     */
    public function test_race_4_concurrent_modify_and_cancel(): void
    {
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-01',
            'capacity' => 2,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-02',
            'capacity' => 2,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);

        // Ingest initial v1 reservation
        $ingest = app(ReservationIngestService::class);
        $v1Evt = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RACE4-TARGET',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-12-01',
            checkOut: '2026-12-02',
            guestName: 'Initial Guest',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-RACE4-V1',
        );
        $ingest->apply($v1Evt);

        // Concurrently run v2 (modify extending stay to 2 nights) and v3 (cancel)
        $barrier = sprintf('%.4f', microtime(true) + 1.2);
        $argsList = [
            [
                'race4',
                $barrier,
                '1',
                '--external_id=RACE4-TARGET',
                '--external_version=2',
                '--type=upsert',
                '--check_in=2026-12-01',
                '--check_out=2026-12-03',
                "--channel={$this->raahi->id}",
            ],
            [
                'race4',
                $barrier,
                '2',
                '--external_id=RACE4-TARGET',
                '--external_version=3',
                '--type=cancel',
                '--check_in=2026-12-01',
                '--check_out=2026-12-02',
                "--channel={$this->raahi->id}",
            ],
        ];

        $this->runWorkersConcurrently($argsList);

        $res = Reservation::where('channel_id', $this->raahi->id)
            ->where('external_id', 'RACE4-TARGET')
            ->first();

        $this->assertNotNull($res);
        $this->assertSame(ReservationStatus::Cancelled, $res->status);
        $this->assertSame(3, $res->external_version);

        $nightsHeld = ReservationNight::where('reservation_id', $res->id)->count();
        $this->assertSame(0, $nightsHeld);

        $inv1 = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', '2026-12-01')->first();
        $inv2 = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', '2026-12-02')->first();

        $this->assertSame(0, $inv1->booked);
        $this->assertSame(0, $inv2->booked);
    }

    /**
     * Race 5: Overlapping multi-night ranges in opposite date orders.
     * Expect no unhandled deadlocks and all invariants intact.
     */
    public function test_race_5_overlapping_multi_night_opposite_order(): void
    {
        $dates = ['2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05'];
        foreach ($dates as $d) {
            RoomInventory::create([
                'room_type_id' => $this->room->id,
                'stay_date' => $d,
                'capacity' => 10,
                'booked' => 0,
                'version' => 1,
                'stop_sell' => false,
            ]);
        }

        $barrier = sprintf('%.4f', microtime(true) + 1.5);
        $argsList = [];

        // 5 workers booking 2026-12-01 to 2026-12-04
        for ($i = 0; $i < 5; $i++) {
            $argsList[] = [
                'race2',
                $barrier,
                (string) $i,
                "--external_id=RACE5-FWD-{$i}",
                '--external_version=1',
                '--check_in=2026-12-01',
                '--check_out=2026-12-04',
                "--channel={$this->raahi->id}",
            ];
        }

        // 5 workers booking 2026-12-03 to 2026-12-06 (overlapping night 2026-12-03)
        for ($i = 5; $i < 10; $i++) {
            $argsList[] = [
                'race2',
                $barrier,
                (string) $i,
                "--external_id=RACE5-BWD-{$i}",
                '--external_version=1',
                '--check_in=2026-12-03',
                '--check_out=2026-12-06',
                "--channel={$this->raahi->id}",
            ];
        }

        $results = $this->runWorkersConcurrently($argsList);

        foreach ($results as $r) {
            $this->assertSame(0, $r['exit_code'], "All workers should complete successfully without unhandled deadlocks.");
        }

        // Invariant assertion across all dates: booked == count(reservation_nights)
        foreach ($dates as $d) {
            $inv = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', $d)->first();
            $heldCount = ReservationNight::where('room_type_id', $this->room->id)->where('stay_date', $d)->count();
            $this->assertSame($inv->booked, $heldCount, "Invariant violated on {$d}: booked ({$inv->booked}) != held ({$heldCount})");
        }
    }

    /**
     * Negative Control 1: Under un-guarded update, Race 1 must oversell.
     */
    public function test_nc1_unguarded_update_causes_race_oversell(): void
    {
        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-12-01',
            'capacity' => 1,
            'booked' => 0,
            'version' => 1,
            'stop_sell' => false,
        ]);

        $workerCount = 10;
        $barrier = sprintf('%.4f', microtime(true) + 1.2);
        $argsList = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $argsList[] = [
                'race1',
                $barrier,
                (string) $i,
                "--external_id=NC1-WORKER-{$i}",
                '--external_version=1',
                '--check_in=2026-12-01',
                '--check_out=2026-12-02',
                "--channel={$this->sarai->id}",
                '--nc=nc1',
            ];
        }

        $this->runWorkersConcurrently($argsList);

        $inv = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', '2026-12-01')->first();
        // In NC1, because update is not guarded by capacity - booked >= 1, multiple workers succeed and oversell
        $this->assertTrue(
            $inv->booked > 1 || $inv->available < 0,
            "NC1 negative control must demonstrate overselling (booked > 1 or available < 0)."
        );
    }
}
