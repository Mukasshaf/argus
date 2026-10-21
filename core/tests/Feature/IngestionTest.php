<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Channels\Dto\ReservationEvent;
use App\Channels\Exceptions\UnmappedCodeException;
use App\Enums\IngestResult;
use App\Enums\ReservationStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestionTest extends TestCase
{
    use RefreshDatabase;

    private ReservationIngestService $service;
    private Hotel $hotel;
    private RoomType $std;
    private RoomType $dlx;
    private RatePlan $epStd;
    private Channel $raahi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReservationIngestService();

        $this->hotel = Hotel::create([
            'name' => 'Lotus Residency',
            'city' => 'Bengaluru',
            'code' => 'LOTUS01',
        ]);

        $this->std = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'code' => 'STD',
            'name' => 'Standard Room',
            'total_rooms' => 10,
        ]);

        $this->dlx = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'code' => 'DLX',
            'name' => 'Deluxe Room',
            'total_rooms' => 6,
        ]);

        $this->epStd = RatePlan::create([
            'room_type_id' => $this->std->id,
            'code' => 'EP',
            'name' => 'EP Room Only',
            'base_price' => 3500,
        ]);

        $this->raahi = Channel::create([
            'code' => 'raahi',
            'name' => 'Raahi Stays',
            'protocol' => 'xml',
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->raahi->id,
            'room_type_id' => $this->std->id,
            'external_room_code' => 'STD',
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->raahi->id,
            'room_type_id' => $this->dlx->id,
            'external_room_code' => 'DLX',
        ]);

        ChannelRateMapping::create([
            'channel_id' => $this->raahi->id,
            'rate_plan_id' => $this->epStd->id,
            'external_rate_code' => 'EP',
        ]);

        // Pre-create room inventory for test dates
        foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'] as $d) {
            RoomInventory::create([
                'room_type_id' => $this->std->id,
                'stay_date' => $d,
                'capacity' => 10,
                'booked' => 0,
                'version' => 1,
            ]);
            RoomInventory::create([
                'room_type_id' => $this->dlx->id,
                'stay_date' => $d,
                'capacity' => 6,
                'booked' => 0,
                'version' => 1,
            ]);
        }
    }

    public function test_ingests_new_reservation_and_diffs_inventory(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1001',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-03', // 2 nights: Nov 1, Nov 2
            guestName: 'Kavita Menon',
            adults: 2,
            totalPrice: 7000,
            currency: 'INR',
            externalEventId: 'EVT-1',
        );

        $result = $this->service->apply($evt);

        $this->assertSame(IngestResult::Applied, $result);

        $this->assertDatabaseHas('reservations', [
            'channel_id' => $this->raahi->id,
            'external_id' => 'RAA-1001',
            'external_version' => 1,
            'status' => 'confirmed',
        ]);

        // Inventory booked incremented for Nov 1 and Nov 2
        $invNov1 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-01')->first();
        $this->assertSame(1, $invNov1->booked);

        $invNov2 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-02')->first();
        $this->assertSame(1, $invNov2->booked);

        // Nov 3 checkout date not booked
        $invNov3 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-03')->first();
        $this->assertSame(0, $invNov3->booked);
    }

    public function test_same_webhook_twice_is_idempotent_duplicate(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1002',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Kavita Menon',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-2',
        );

        $res1 = $this->service->apply($evt);
        $this->assertSame(IngestResult::Applied, $res1);

        // Deliver same version second time
        $res2 = $this->service->apply($evt);
        $this->assertSame(IngestResult::Duplicate, $res2);

        $inv = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-01')->first();
        $this->assertSame(1, $inv->booked, 'Should not double count on duplicate delivery');
    }

    public function test_out_of_order_lower_version_is_ignored(): void
    {
        $evtV2 = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1003',
            externalVersion: 2,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Kavita Menon V2',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-3-v2',
        );

        $evtV1 = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1003',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Kavita Menon V1',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-3-v1',
        );

        $this->service->apply($evtV2);
        $res = $this->service->apply($evtV1);

        $this->assertSame(IngestResult::Duplicate, $res);

        $r = Reservation::where('external_id', 'RAA-1003')->first();
        $this->assertSame('Kavita Menon V2', $r->guest_name);
        $this->assertSame(2, $r->external_version);
    }

    public function test_modify_extends_stay_and_updates_booked(): void
    {
        $evtV1 = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1004',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02', // 1 night
            guestName: 'Sanjay Dutt',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-4-v1',
        );

        $this->service->apply($evtV1);

        // Modify extending to Nov 3 (2 nights: Nov 1, Nov 2)
        $evtV2 = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1004',
            externalVersion: 2,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-03',
            guestName: 'Sanjay Dutt',
            adults: 1,
            totalPrice: 7000,
            currency: 'INR',
            externalEventId: 'EVT-4-v2',
        );

        $this->service->apply($evtV2);

        $invNov1 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-01')->first();
        $invNov2 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-02')->first();

        $this->assertSame(1, $invNov1->booked);
        $this->assertSame(1, $invNov2->booked);
    }

    public function test_cancellation_releases_all_held_nights(): void
    {
        $evtCreate = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1005',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-03',
            guestName: 'Meera Nair',
            adults: 1,
            totalPrice: 7000,
            currency: 'INR',
            externalEventId: 'EVT-5-v1',
        );

        $this->service->apply($evtCreate);

        $evtCancel = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1005',
            externalVersion: 2,
            type: 'cancel',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-03',
            guestName: 'Meera Nair',
            adults: 1,
            totalPrice: 7000,
            currency: 'INR',
            externalEventId: 'EVT-5-v2',
        );

        $this->service->apply($evtCancel);

        $res = Reservation::where('external_id', 'RAA-1005')->first();
        $this->assertSame(ReservationStatus::Cancelled, $res->status);
        $this->assertCount(0, $res->nights);

        $invNov1 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-01')->first();
        $invNov2 = RoomInventory::where('room_type_id', $this->std->id)->where('stay_date', '2026-11-02')->first();
        $this->assertSame(0, $invNov1->booked);
        $this->assertSame(0, $invNov2->booked);
    }

    public function test_cancel_before_create_creates_cancelled_tombstone(): void
    {
        // Out of order: cancel arrives before initial create
        $evtCancel = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1006',
            externalVersion: 2,
            type: 'cancel',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Late Guest',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-6-v2',
        );

        $this->service->apply($evtCancel);

        $res = Reservation::where('external_id', 'RAA-1006')->first();
        $this->assertSame(ReservationStatus::Cancelled, $res->status);
        $this->assertSame(2, $res->external_version);

        // Later arrival of lower version 1 is ignored as duplicate
        $evtCreateLate = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-1006',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'STD',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Late Guest',
            adults: 1,
            totalPrice: 3500,
            currency: 'INR',
            externalEventId: 'EVT-6-v1',
        );

        $result = $this->service->apply($evtCreateLate);
        $this->assertSame(IngestResult::Duplicate, $result);
    }

    public function test_unmapped_code_throws_permanent_exception_and_raises_alert(): void
    {
        $evt = new ReservationEvent(
            channelId: $this->raahi->id,
            externalId: 'RAA-9999',
            externalVersion: 1,
            type: 'upsert',
            roomCode: 'NON_EXISTENT_ROOM',
            rateCode: 'EP',
            checkIn: '2026-11-01',
            checkOut: '2026-11-02',
            guestName: 'Test',
            adults: 1,
            totalPrice: 1000,
            currency: 'INR',
            externalEventId: 'EVT-9999',
        );

        $this->expectException(UnmappedCodeException::class);
        $this->service->apply($evt);

        $this->assertDatabaseHas('alerts', [
            'type' => 'unmapped_code',
        ]);
    }
}
