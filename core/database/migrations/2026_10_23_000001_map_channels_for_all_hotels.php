<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $channels = Channel::all();
        if ($channels->isEmpty()) {
            return;
        }

        DB::table('channel_rate_mappings')->delete();
        DB::table('channel_room_mappings')->delete();

        $hotels = Hotel::with('roomTypes.ratePlans')->get();

        foreach ($hotels as $hotel) {
            $prefix = match($hotel->code) {
                'LOTUS01' => '',
                'MYSORE01' => 'MYS_',
                'MALABAR01' => 'MAL_',
                default => strtoupper(substr($hotel->code, 0, 4)) . '_',
            };

            foreach ($hotel->roomTypes as $roomType) {
                $extRoomCode = $prefix === '' ? $roomType->code : $prefix . $roomType->code;

                foreach ($channels as $channel) {
                    ChannelRoomMapping::create([
                        'channel_id' => $channel->id,
                        'room_type_id' => $roomType->id,
                        'external_room_code' => $extRoomCode,
                    ]);
                }

                foreach ($roomType->ratePlans as $ratePlan) {
                    $extRateCode = ($prefix === '' && $roomType->code === 'STD')
                        ? $ratePlan->code
                        : ($prefix === '' ? $roomType->code . '_' . $ratePlan->code : $prefix . $roomType->code . '_' . $ratePlan->code);

                    foreach ($channels as $channel) {
                        ChannelRateMapping::create([
                            'channel_id' => $channel->id,
                            'rate_plan_id' => $ratePlan->id,
                            'external_rate_code' => $extRateCode,
                        ]);
                    }
                }
            }
        }

        try {
            Artisan::call('argus:extend-inventory', ['--days' => 60]);
        } catch (\Throwable $e) {
            // Silently continue
        }
    }

    public function down(): void
    {
    }
};
