<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Services\Nights;
use App\Services\RateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompareController extends Controller
{
    /**
     * Public comparison page: Compare rates across all active channels for a hotel stay.
     * Cached 60 seconds with Cache::remember.
     */
    public function index(Request $request, string $hotelCode): View
    {
        $hotel = Hotel::where('code', $hotelCode)
            ->orWhere('id', is_numeric($hotelCode) ? (int) $hotelCode : 0)
            ->firstOrFail();

        $today = Carbon::today('Asia/Kolkata');
        $checkIn = $request->query('checkin', $today->copy()->addDay()->toDateString());
        $checkOut = $request->query('checkout', $today->copy()->addDays(2)->toDateString());
        $adults = max(1, (int) $request->query('adults', 2));

        $nights = Nights::between($checkIn, $checkOut);
        if (empty($nights)) {
            $nights = [$checkIn];
            $checkOut = Carbon::parse($checkIn)->addDay()->toDateString();
        }

        $cacheKey = "compare_{$hotel->id}_{$checkIn}_{$checkOut}_{$adults}";

        $results = Cache::remember($cacheKey, 60, function () use ($hotel, $nights, $checkIn, $checkOut) {
            $channels = Channel::where('status', ChannelStatus::Active)->get();
            $roomTypes = $hotel->roomTypes()->with('ratePlans')->get();

            $offers = [];

            foreach ($roomTypes as $roomType) {
                // Check minimum available inventory across all nights
                $invRows = DB::table('room_inventory')
                    ->where('room_type_id', $roomType->id)
                    ->whereIn('stay_date', $nights)
                    ->get();

                if ($invRows->count() !== count($nights)) {
                    continue;
                }

                $isAvailable = true;
                foreach ($invRows as $inv) {
                    if ($inv->stop_sell || $inv->available <= 0) {
                        $isAvailable = false;
                        break;
                    }
                }

                if (!$isAvailable) {
                    continue;
                }

                foreach ($roomType->ratePlans as $ratePlan) {
                    // Indexed query on (rate_plan_id, stay_date)
                    $rateRows = DB::table('rate_prices')
                        ->where('rate_plan_id', $ratePlan->id)
                        ->whereIn('stay_date', $nights)
                        ->get();

                    if ($rateRows->count() !== count($nights)) {
                        continue;
                    }

                    $basePriceSum = (int) $rateRows->sum('price');

                    foreach ($channels as $channel) {
                        $hasRoomMapping = ChannelRoomMapping::where('channel_id', $channel->id)
                            ->where('room_type_id', $roomType->id)
                            ->exists();

                        $hasRateMapping = ChannelRateMapping::where('channel_id', $channel->id)
                            ->where('rate_plan_id', $ratePlan->id)
                            ->exists();

                        if (!$hasRoomMapping || !$hasRateMapping) {
                            continue;
                        }

                        $channelTotal = RateService::applyMarkup($basePriceSum, $channel->markup_bps);

                        $offers[] = [
                            'channel_id' => $channel->id,
                            'channel_code' => $channel->code,
                            'channel_name' => $channel->name,
                            'protocol' => $channel->protocol->value,
                            'room_type_name' => $roomType->name,
                            'room_type_code' => $roomType->code,
                            'rate_plan_name' => $ratePlan->name,
                            'rate_plan_code' => $ratePlan->code,
                            'base_price' => $basePriceSum,
                            'total_price' => $channelTotal,
                            'markup_bps' => $channel->markup_bps,
                            'nights_count' => count($nights),
                            'price_per_night' => (int) round($channelTotal / count($nights)),
                        ];
                    }
                }
            }

            usort($offers, fn($a, $b) => $a['total_price'] <=> $b['total_price']);

            return $offers;
        });

        return view('compare', [
            'hotel' => $hotel,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'adults' => $adults,
            'nightsCount' => count($nights),
            'offers' => $results,
        ]);
    }
}
