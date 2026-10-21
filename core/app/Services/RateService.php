<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RatePrice;
use Illuminate\Support\Facades\DB;

class RateService
{
    /**
     * @param list<string> $dates
     */
    public function setPrice(int $ratePlanId, array $dates, int $price): void
    {
        if (empty($dates)) {
            return;
        }

        DB::transaction(function () use ($ratePlanId, $dates, $price) {
            foreach (array_chunk($dates, 500) as $chunk) {
                DB::table('rate_prices')
                    ->where('rate_plan_id', $ratePlanId)
                    ->whereIn('stay_date', $chunk)
                    ->update([
                        'price' => $price,
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }
        });

        SyncKick::allChannels();
    }

    /**
     * Calculates marked up price in whole rupees with standard rounding.
     * Formula: intdiv(price * (10000 + markup_bps) + 5000, 10000)
     */
    public static function applyMarkup(int $basePrice, int $markupBps): int
    {
        return intdiv($basePrice * (10000 + $markupBps) + 5000, 10000);
    }
}
