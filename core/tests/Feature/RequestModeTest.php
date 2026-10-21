<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Channels\Dto\ReservationEvent;
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

class RequestModeTest extends TestCase
{
    use RefreshDatabase;

    private RealtimeBookingService $service;
    private Channel $sarai;
    private RoomType $std;
    private RatePlan $ep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RealtimeBookingService();

        $hotel = Hotel::create([
            'name' => 'Lotus Residency',
            'city' => 'Bengaluru',
            'code' => 'LOTUS01',
        ]);

        $this->std = RoomType::create([
            'hotel_id' => $hotel->id,
            'code' => 'STD',
            'name' => 'Standard Room',
            'total_rooms' => 1,
        ]);

        $this->ep = RatePlan::create([
            'room_type_id' => $this->std->id,
            'code' => 'EP',
            'name' => 'EP Room Only',
            'base_price' => 3500,
        ]);

        $this->sarai = Channel::create([
            'code' => 'sarai',
            'name' => 'Sarai Direct',
            'protocol' => 'json',
            'supports_realtime' => true,
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->sarai->id,
            'room_type_id' => $this->std->id,
            'external_room_code' => 'STD',
        ]);

        ChannelRateMapping::create([
            'channel_id' => $this->sarai->id,
            'rate_plan_id' => $this->ep->id,
            'external_rate_code' => 'EP',
        ]);

        // 1 key capacity for 2026-11-10
        RoomInventory::create([
            'room_type_id' => $this->std->id,
            'stay_date' => '2026-11-10',
            'capacity' => 1,
            'booked' => 0,
            'stop_sell' => 0,
            'version' => 1,
        ]);
    }

    public function test_booking_succeeds_when_room_available(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->sarai->id,
            externalId: 'DIR-5001',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-10',
            checkOut: '2026-11-11',
            guestName: 'Deepa Roy',
            adults: 1,
            totalPrice: 3920,
            currency: 'INR',
            externalEventId: 'EVT-DIR-1',
        );

        $res = $this->service->book($evt);

        $this->assertSame(201, $res['status']);
        $this->assertSame('confirmed', $res['data']['status']);

        $inv = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-10')->first();
        $this->assertSame(1, $inv->booked);
        $this->assertSame(0, $inv->available);
    }

    public function test_sold_out_returns_409(): void
    {
        // First booking consumes the 1 available key
        $evt1 = new ReservationEvent(
            channelId: $this->sarai->id,
            externalId: 'DIR-5002',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-10',
            checkOut: '2026-11-11',
            guestName: 'Guest One',
            adults: 1,
            totalPrice: 3920,
            currency: 'INR',
            externalEventId: 'EVT-5002',
        );
        $res1 = $this->service->book($evt1);
        $this->assertSame(201, $res1['status']);

        // Second booking arrives for same night -> must be rejected with 409
        $evt2 = new ReservationEvent(
            channelId: $this->sarai->id,
            externalId: 'DIR-5003',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-10',
            checkOut: '2026-11-11',
            guestName: 'Guest Two',
            adults: 1,
            totalPrice: 3920,
            currency: 'INR',
            externalEventId: 'EVT-5003',
        );
        $res2 = $this->service->book($evt2);

        $this->assertSame(409, $res2['status']);
        $this->assertSame('sold_out', $res2['data']['reason']);

        $inv = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-10')->first();
        $this->assertSame(1, $inv->booked);
        $this->assertSame(0, $inv->available);
    }

    public function test_retried_request_returns_original_201_idempotently(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->sarai->id,
            externalId: 'DIR-5004',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-10',
            checkOut: '2026-11-11',
            guestName: 'Same Guest',
            adults: 1,
            totalPrice: 3920,
            currency: 'INR',
            externalEventId: 'EVT-5004',
        );

        $res1 = $this->service->book($evt);
        $this->assertSame(201, $res1['status']);

        // Retry same booking ID
        $res2 = $this->service->book($evt);
        $this->assertSame(201, $res2['status']);
    }

    public function test_later_polled_copy_is_ignored_as_duplicate(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->sarai->id,
            externalId: 'DIR-5005',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-10',
            checkOut: '2026-11-11',
            guestName: 'Polled Duplicate Guest',
            adults: 1,
            totalPrice: 3920,
            currency: 'INR',
            externalEventId: 'EVT-5005',
        );

        // Instant book via request mode
        $this->service->book($evt);

        // Later arrival via poller (ReservationIngestService)
        $ingestService = new ReservationIngestService();
        $result = $ingestService->apply($evt);

        $this->assertSame(\App\Enums\IngestResult::Duplicate, $result);
    }
}
