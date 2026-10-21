<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class Nights
{
    /**
     * Returns list of stay dates [checkIn, checkOut) with checkout excluded.
     *
     * @return list<string>
     */
    public static function between(string|Carbon $checkIn, string|Carbon $checkOut): array
    {
        $start = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut);

        if ($end->lessThanOrEqualTo($start)) {
            return [];
        }

        // Checkout date is excluded: [checkIn, checkOut)
        $period = CarbonPeriod::create($start, $end->copy()->subDay());
        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
