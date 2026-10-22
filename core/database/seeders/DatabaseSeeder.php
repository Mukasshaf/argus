<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChannelProtocol;
use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed user (Sunita Rao, GM Operations)
        User::firstOrCreate(
            ['email' => 'sunita.rao@lotusresidency.in'],
            [
                'name' => 'Sunita Rao',
                'password' => Hash::make('secret'),
                'email_verified_at' => now(),
            ]
        );

        // Also seed default admin
        User::firstOrCreate(
            ['email' => 'admin@staybridge.local'],
            [
                'name' => 'StayBridge Admin',
                'password' => Hash::make('secret'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Hotel
        $hotel = Hotel::firstOrCreate(
            ['code' => 'LOTUS01'],
            [
                'name' => 'Lotus Residency',
                'city' => 'Bengaluru',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
            ]
        );

        // 3. Room types: Standard (STD, 10 rooms), Deluxe (DLX, 6 rooms), Suite (STE, 2 rooms)
        $std = RoomType::firstOrCreate(
            ['hotel_id' => $hotel->id, 'code' => 'STD'],
            ['name' => 'Standard Room', 'total_rooms' => 10]
        );

        $dlx = RoomType::firstOrCreate(
            ['hotel_id' => $hotel->id, 'code' => 'DLX'],
            ['name' => 'Deluxe Room', 'total_rooms' => 6]
        );

        $ste = RoomType::firstOrCreate(
            ['hotel_id' => $hotel->id, 'code' => 'STE'],
            ['name' => 'Heritage Suite', 'total_rooms' => 2]
        );

        // 4. Rate plans: EP (room only) and CP (breakfast included)
        // Base prices: STD 3500 and 4000, DLX 5200 and 5700, STE 9800 and 10500
        $ratePlans = [
            ['room_type' => $std, 'code' => 'EP', 'name' => 'EP (Room Only)', 'price' => 3500],
            ['room_type' => $std, 'code' => 'CP', 'name' => 'CP (Breakfast Inc.)', 'price' => 4000],
            ['room_type' => $dlx, 'code' => 'EP', 'name' => 'EP Rate', 'price' => 5200],
            ['room_type' => $dlx, 'code' => 'CP', 'name' => 'CP Rate', 'price' => 5700],
            ['room_type' => $ste, 'code' => 'EP', 'name' => 'EP Suite', 'price' => 9800],
            ['room_type' => $ste, 'code' => 'CP', 'name' => 'CP Suite', 'price' => 10500],
        ];

        $savedRatePlans = [];
        foreach ($ratePlans as $rp) {
            $saved = RatePlan::firstOrCreate(
                ['room_type_id' => $rp['room_type']->id, 'code' => $rp['code']],
                ['name' => $rp['name'], 'base_price' => $rp['price']]
            );
            $savedRatePlans[$rp['room_type']->code . '_' . $rp['code']] = $saved;
        }

        // 5. Channels:
        // raahi (XML, markup 1000 bps)
        $raahi = Channel::firstOrCreate(
            ['code' => 'raahi'],
            [
                'name' => 'Raahi Stays',
                'protocol' => ChannelProtocol::XML,
                'base_url' => env('RAAHI_BASE_URL', config('services.sim.base_url') . '/raahi'),
                'credentials' => ['api_key' => 'raahi_secret_api_key_2026'],
                'webhook_secret' => 'raahi_webhook_secret_hmac_sha256',
                'markup_bps' => 1000,
                'supports_webhook' => true,
                'supports_polling' => false,
                'supports_realtime' => false,
                'status' => ChannelStatus::Active,
            ]
        );

        // sarai (JSON, markup 1200 bps)
        $sarai = Channel::firstOrCreate(
            ['code' => 'sarai'],
            [
                'name' => 'Sarai Direct',
                'protocol' => ChannelProtocol::JSON,
                'base_url' => env('SARAI_BASE_URL', config('services.sim.base_url') . '/sarai'),
                'credentials' => [
                    'client_id' => 'sarai_client_id_lotus',
                    'client_secret' => 'sarai_client_secret_xyz',
                ],
                'webhook_secret' => 'sarai_webhook_secret_hmac_sha256',
                'markup_bps' => 1200,
                'supports_webhook' => false,
                'supports_polling' => true,
                'supports_realtime' => true,
                'status' => ChannelStatus::Active,
            ]
        );

        // mela (CSV, markup 800 bps)
        $mela = Channel::firstOrCreate(
            ['code' => 'mela'],
            [
                'name' => 'Mela Bulk',
                'protocol' => ChannelProtocol::CSV,
                'base_url' => null,
                'credentials' => [],
                'webhook_secret' => 'mela_file_secret_placeholder',
                'markup_bps' => 800,
                'supports_webhook' => false,
                'supports_polling' => true,
                'supports_realtime' => false,
                'status' => ChannelStatus::Active,
            ]
        );

        // 6. External codes: rooms STD, DLX, STE; rates EP, CP on every channel
        $allChannels = [$raahi, $sarai, $mela];
        $allRooms = [$std, $dlx, $ste];

        foreach ($allChannels as $ch) {
            foreach ($allRooms as $rm) {
                ChannelRoomMapping::firstOrCreate(
                    ['channel_id' => $ch->id, 'room_type_id' => $rm->id],
                    ['external_room_code' => $rm->code]
                );
            }

            foreach ($savedRatePlans as $key => $plan) {
                ChannelRateMapping::updateOrCreate(
                    ['channel_id' => $ch->id, 'external_rate_code' => $plan->code],
                    ['rate_plan_id' => $plan->id]
                );
            }
        }

        // 7. Pre-extend inventory for initial 30 days
        Artisan::call('staybridge:extend-inventory', ['--days' => 30]);
    }
}
