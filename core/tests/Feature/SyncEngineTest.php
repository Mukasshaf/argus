<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Channels\ChannelAdapterFactory;
use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Exceptions\TransientChannelException;
use App\Enums\ChannelStatus;
use App\Enums\SyncKind;
use App\Jobs\SyncChannelJob;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncState;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\SyncPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncEngineTest extends TestCase
{
    use RefreshDatabase;

    private Channel $raahi;
    private RoomType $room;
    private ChannelSyncState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $hotel = Hotel::create([
            'name' => 'Lotus Residency',
            'city' => 'Bengaluru',
            'code' => 'LOTUS01',
        ]);

        $this->room = RoomType::create([
            'hotel_id' => $hotel->id,
            'code' => 'STD',
            'name' => 'Standard Room',
            'total_rooms' => 10,
        ]);

        $this->raahi = Channel::create([
            'code' => 'raahi',
            'name' => 'Raahi Stays',
            'protocol' => 'xml',
            'base_url' => 'http://sim:8081/raahi',
            'status' => ChannelStatus::Active,
        ]);

        ChannelRoomMapping::create([
            'channel_id' => $this->raahi->id,
            'room_type_id' => $this->room->id,
            'external_room_code' => 'STD',
        ]);

        $today = now()->timezone('Asia/Kolkata')->toDateString();

        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => $today,
            'capacity' => 10,
            'booked' => 0,
            'version' => 5,
        ]);

        $this->state = ChannelSyncState::create([
            'channel_id' => $this->raahi->id,
            'kind' => SyncKind::Availability,
            'subject_id' => $this->room->id,
            'stay_date' => $today,
            'pushed_version' => 1, // dirty (version 5 > pushed_version 1)
        ]);
    }

    public function test_transient_500_increments_consecutive_failures(): void
    {
        Http::fake([
            '*/raahi/availability' => Http::response('<Errors><Error Code="500"/></Errors>', 500),
        ]);

        $job = new SyncChannelJob($this->raahi->id);
        $job->handle(app(ChannelAdapterFactory::class), app(SyncPlanner::class));

        $this->raahi->refresh();
        $this->assertSame(1, $this->raahi->consecutive_failures);
    }

    public function test_circuit_opens_after_5_failures(): void
    {
        Http::fake([
            '*/raahi/availability' => Http::response('<Errors><Error Code="500"/></Errors>', 500),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $job = new SyncChannelJob($this->raahi->id);
            $job->handle(app(ChannelAdapterFactory::class), app(SyncPlanner::class));
        }

        $this->raahi->refresh();
        $this->assertSame(5, $this->raahi->consecutive_failures);
        $this->assertSame(ChannelStatus::Degraded, $this->raahi->status);
        $this->assertTrue($this->raahi->circuitIsOpen());
    }

    public function test_permanent_400_quarantines_state(): void
    {
        Http::fake([
            '*/raahi/availability' => Http::response('<Errors><Error Code="400" ShortText="Bad schema"/></Errors>', 400),
        ]);

        $job = new SyncChannelJob($this->raahi->id);
        $job->handle(app(ChannelAdapterFactory::class), app(SyncPlanner::class));

        $this->state->refresh();
        $this->assertNotNull($this->state->blocked_at);
        $this->assertNotNull($this->state->last_error);

        // Alert raised
        $this->assertDatabaseHas('alerts', [
            'type' => 'channel_blocked',
        ]);
    }

    public function test_successful_push_advances_pushed_version_to_seen_version(): void
    {
        Http::fake([
            '*/raahi/availability' => Http::response('<OTA_HotelAvailNotifRS><Success/></OTA_HotelAvailNotifRS>', 200),
        ]);

        $job = new SyncChannelJob($this->raahi->id);
        $job->handle(app(ChannelAdapterFactory::class), app(SyncPlanner::class));

        $this->state->refresh();
        $this->assertSame(5, $this->state->pushed_version);
        $this->raahi->refresh();
        $this->assertSame(0, $this->raahi->consecutive_failures);
    }
}
