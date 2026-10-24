<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelStatus;
use App\Enums\SyncKind;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncState;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use App\Models\RoomInventory;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtendInventory extends Command
{
    protected $signature = 'argus:extend-inventory {--days=540}';
    protected $description = 'Pre-generate room_inventory, rate_prices, and channel_sync_state rows for mapped subjects';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $hotel = Hotel::first();
        $timezone = $hotel ? $hotel->timezone : 'Asia/Kolkata';

        $today = now()->timezone($timezone)->startOfDay();
        $endDate = $today->copy()->addDays($days);
        $period = CarbonPeriod::create($today, $endDate);

        $this->info("Extending inventory from {$today->toDateString()} to {$endDate->toDateString()} ({$days} days)...");

        $roomTypes = RoomType::all();
        $ratePlans = RatePlan::all();
        $channels = Channel::where('status', '!=', ChannelStatus::Paused)->get();

        foreach ($roomTypes as $roomType) {
            $existingDates = RoomInventory::where('room_type_id', $roomType->id)
                ->whereBetween('stay_date', [$today->toDateString(), $endDate->toDateString()])
                ->pluck('stay_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->all();
            $existingSet = array_flip($existingDates);

            $inserts = [];
            foreach ($period as $date) {
                $dStr = $date->toDateString();
                if (!isset($existingSet[$dStr])) {
                    $inserts[] = [
                        'room_type_id' => $roomType->id,
                        'stay_date' => $dStr,
                        'capacity' => $roomType->total_rooms,
                        'booked' => 0,
                        'stop_sell' => 0,
                        'version' => 1,
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('room_inventory')->insert($chunk);
            }
        }

        foreach ($ratePlans as $ratePlan) {
            $existingDates = RatePrice::where('rate_plan_id', $ratePlan->id)
                ->whereBetween('stay_date', [$today->toDateString(), $endDate->toDateString()])
                ->pluck('stay_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->all();
            $existingSet = array_flip($existingDates);

            $inserts = [];
            foreach ($period as $date) {
                $dStr = $date->toDateString();
                if (!isset($existingSet[$dStr])) {
                    $inserts[] = [
                        'rate_plan_id' => $ratePlan->id,
                        'stay_date' => $dStr,
                        'price' => $ratePlan->base_price,
                        'version' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('rate_prices')->insert($chunk);
            }
        }

        // Extend channel_sync_state for mapped subjects only (starting at pushed_version = -1)
        foreach ($channels as $channel) {
            $mappedRoomIds = ChannelRoomMapping::where('channel_id', $channel->id)->pluck('room_type_id')->all();
            foreach ($mappedRoomIds as $roomTypeId) {
                $existing = ChannelSyncState::where('channel_id', $channel->id)
                    ->where('kind', SyncKind::Availability)
                    ->where('subject_id', $roomTypeId)
                    ->whereBetween('stay_date', [$today->toDateString(), $endDate->toDateString()])
                    ->pluck('stay_date')
                    ->map(fn($d) => Carbon::parse($d)->toDateString())
                    ->all();
                $existingSet = array_flip($existing);

                $inserts = [];
                foreach ($period as $date) {
                    $dStr = $date->toDateString();
                    if (!isset($existingSet[$dStr])) {
                        $inserts[] = [
                            'channel_id' => $channel->id,
                            'kind' => SyncKind::Availability->value,
                            'subject_id' => $roomTypeId,
                            'stay_date' => $dStr,
                            'pushed_version' => -1,
                            'attempts' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('channel_sync_state')->insert($chunk);
                }
            }

            $mappedRateIds = ChannelRateMapping::where('channel_id', $channel->id)->pluck('rate_plan_id')->all();
            foreach ($mappedRateIds as $ratePlanId) {
                $existing = ChannelSyncState::where('channel_id', $channel->id)
                    ->where('kind', SyncKind::Rate)
                    ->where('subject_id', $ratePlanId)
                    ->whereBetween('stay_date', [$today->toDateString(), $endDate->toDateString()])
                    ->pluck('stay_date')
                    ->map(fn($d) => Carbon::parse($d)->toDateString())
                    ->all();
                $existingSet = array_flip($existing);

                $inserts = [];
                foreach ($period as $date) {
                    $dStr = $date->toDateString();
                    if (!isset($existingSet[$dStr])) {
                        $inserts[] = [
                            'channel_id' => $channel->id,
                            'kind' => SyncKind::Rate->value,
                            'subject_id' => $ratePlanId,
                            'stay_date' => $dStr,
                            'pushed_version' => -1,
                            'attempts' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('channel_sync_state')->insert($chunk);
                }
            }
        }

        $this->info("Inventory and sync states successfully extended.");
        return self::SUCCESS;
    }
}
