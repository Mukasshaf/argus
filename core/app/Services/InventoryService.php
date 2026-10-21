<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RoomInventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * @param list<string> $dates
     */
    public function setCapacity(int $roomTypeId, array $dates, int $capacity): void
    {
        if (empty($dates)) {
            return;
        }

        DB::transaction(function () use ($roomTypeId, $dates, $capacity) {
            // Check: Reject capacity below current booked in the extranet (Section 7.2)
            $maxBooked = DB::table('room_inventory')
                ->where('room_type_id', $roomTypeId)
                ->whereIn('stay_date', $dates)
                ->max('booked');

            if ($maxBooked !== null && $capacity < (int) $maxBooked) {
                throw new InvalidArgumentException(
                    "Capacity ({$capacity}) cannot be lower than existing bookings ({$maxBooked})."
                );
            }

            foreach (array_chunk($dates, 500) as $chunk) {
                DB::table('room_inventory')
                    ->where('room_type_id', $roomTypeId)
                    ->whereIn('stay_date', $chunk)
                    ->update([
                        'capacity' => $capacity,
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }
        });

        SyncKick::allChannels();
    }

    /**
     * @param list<string> $dates
     */
    public function setStopSell(int $roomTypeId, array $dates, bool $stopSell): void
    {
        if (empty($dates)) {
            return;
        }

        DB::transaction(function () use ($roomTypeId, $dates, $stopSell) {
            foreach (array_chunk($dates, 500) as $chunk) {
                DB::table('room_inventory')
                    ->where('room_type_id', $roomTypeId)
                    ->whereIn('stay_date', $chunk)
                    ->update([
                        'stop_sell' => $stopSell ? 1 : 0,
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }
        });

        SyncKick::allChannels();
    }

    /**
     * Filters date range by day of week using Carbon isoWeekday (1=Mon ... 7=Sun).
     *
     * @param list<int> $weekdays 1-7
     * @return list<string>
     */
    public function filterDates(string $startDate, string $endDate, array $weekdays): array
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];

        foreach ($period as $date) {
            if (empty($weekdays) || in_array($date->isoWeekday(), $weekdays, true)) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }
}
