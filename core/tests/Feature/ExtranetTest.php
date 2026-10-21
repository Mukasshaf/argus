<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ExtranetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Hotel $hotel;
    private RoomType $room;
    private RatePlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Sunita Rao',
            'email' => 'sunita.rao@lotusresidency.in',
            'password' => bcrypt('secret'),
        ]);

        $this->hotel = Hotel::create([
            'name' => 'Lotus Residency',
            'city' => 'Bengaluru',
            'code' => 'LOTUS01',
        ]);

        $this->room = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'code' => 'STD',
            'name' => 'Standard Room',
            'total_rooms' => 10,
        ]);

        $this->plan = RatePlan::create([
            'room_type_id' => $this->room->id,
            'code' => 'EP',
            'name' => 'EP Room Only',
            'base_price' => 3500,
        ]);

        RoomInventory::create([
            'room_type_id' => $this->room->id,
            'stay_date' => '2026-11-20',
            'capacity' => 10,
            'booked' => 3,
            'version' => 1,
        ]);

        RatePrice::create([
            'rate_plan_id' => $this->plan->id,
            'stay_date' => '2026-11-20',
            'price' => 3500,
            'version' => 1,
        ]);
    }

    public function test_capacity_update_bumps_version(): void
    {
        $service = new InventoryService();
        $service->setCapacity($this->room->id, ['2026-11-20'], 12);

        $inv = RoomInventory::where('room_type_id', $this->room->id)->where('stay_date', '2026-11-20')->first();
        $this->assertSame(12, $inv->capacity);
        $this->assertSame(2, $inv->version);
    }

    public function test_capacity_below_booked_is_rejected(): void
    {
        $service = new InventoryService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be lower than existing bookings/i');

        // booked is 3; attempting to set capacity to 2 must fail
        $service->setCapacity($this->room->id, ['2026-11-20'], 2);
    }

    public function test_rate_price_update_bumps_version(): void
    {
        $service = new RateService();
        $service->setPrice($this->plan->id, ['2026-11-20'], 4200);

        $rp = RatePrice::where('rate_plan_id', $this->plan->id)->where('stay_date', '2026-11-20')->first();
        $this->assertSame(4200, $rp->price);
        $this->assertSame(2, $rp->version);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/extranet/calendar');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_calendar(): void
    {
        $response = $this->actingAs($this->user)->get('/extranet/calendar');
        $response->assertStatus(200);
        $response->assertSee('Lotus Residency');
        $response->assertSee('Standard Room (STD)');
    }
}
