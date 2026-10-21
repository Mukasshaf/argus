<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Channels\Dto\ReservationEvent;
use App\Enums\IngestResult;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\RealtimeBookingService;
use App\Services\ReservationIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegativeControlsTest extends TestCase
{
    use RefreshDatabase;

    private Channel $ch;
    private RoomType $room;
    private RatePlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $hotel = Hotel::create(['name' => 'Lotus Residency', 'city' => 'BLR', 'code' => 'LOTUS01']);
        $this->room = RoomType::create(['hotel_id' => $hotel->id, 'code' => 'STD', 'name' => 'Std', 'total_rooms' => 1]);
        $this->plan = RatePlan::create(['room_type_id' => $this->room->id, 'code' => 'EP', 'name' => 'EP', 'base_price' => 3500]);
        $this->ch = Channel::create(['code' => 'sarai', 'name' => 'Sarai', 'protocol' => 'json']);

        ChannelRoomMapping::create(['channel_id' => $this->ch->id, 'room_type_id' => $this->room->id, 'external_room_code' => 'STD']);
        ChannelRateMapping::create(['channel_id' => $this->ch->id, 'rate_plan_id' => $this->plan->id, 'external_rate_code' => 'EP']);

        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-11-25',
            'capacity' => 1,
            'booked' => 0,
            'version' => 1,
        ]);
    }

    public function test_nc1_unguarded_update_allows_overselling_under_simulated_overlap(): void
    {
        RealtimeBookingService::$useUnguardedUpdate = true;
        $svc = new RealtimeBookingService();

        $evt1 = new ReservationEvent($this->ch->id, 'NC1-A', 1, 'upsert', 'STD', 'EP', '2026-11-25', '2026-11-26', 'Guest A', 1, 3500, 'INR', 'E1');
        $evt2 = new ReservationEvent($this->ch->id, 'NC1-B', 1, 'upsert', 'STD', 'EP', '2026-11-25', '2026-11-26', 'Guest B', 1, 3500, 'INR', 'E2');

        $res1 = $svc->book($evt1);
        $this->assertSame(201, $res1['status']);

        // In NC1 mode, if the guard is bypassed or capacity check sees 1 before both commit,
        // it updates unconditionally.
        RealtimeBookingService::$useUnguardedUpdate = false;
    }

    public function test_nc2_skipping_claim_lock_fails_duplicate_detection_under_race(): void
    {
        ReservationIngestService::$skipClaimAndLock = true;
        $svc = new ReservationIngestService();

        $evt = new ReservationEvent($this->ch->id, 'NC2-DUP', 1, 'upsert', 'STD', 'EP', '2026-11-25', '2026-11-26', 'Guest Dup', 1, 3500, 'INR', 'E-DUP');

        // When claim is skipped, race conditions can record multiple bookings
        $res = $svc->apply($evt);
        $this->assertSame(IngestResult::Applied, $res);

        ReservationIngestService::$skipClaimAndLock = false;
    }

    public function test_nc3_read_modify_write_loses_updates(): void
    {
        ReservationIngestService::$useReadModifyWrite = true;
        $svc = new ReservationIngestService();

        $evt = new ReservationEvent($this->ch->id, 'NC3-RMW', 1, 'upsert', 'STD', 'EP', '2026-11-25', '2026-11-26', 'Guest RMW', 1, 3500, 'INR', 'E-RMW');
        $res = $svc->apply($evt);
        $this->assertSame(IngestResult::Applied, $res);

        $inv = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', '2026-11-25')->first();
        $this->assertSame(1, $inv->booked);

        ReservationIngestService::$useReadModifyWrite = false;
    }
}
