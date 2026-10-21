<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\AlertType;
use App\Enums\ChannelStatus;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Channel;
use App\Models\ChannelSyncState;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use App\Models\Reservation;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\InventoryService;
use App\Services\RateService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class CalendarController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private RateService $rateService,
    ) {}

    public function index(Request $request): View
    {
        $hotel = Hotel::first();
        $timezone = $hotel ? $hotel->timezone : 'Asia/Kolkata';
        $today = now()->timezone($timezone)->startOfDay();

        $startDateStr = $request->query('start_date');
        $startDate = $startDateStr ? Carbon::parse($startDateStr)->timezone($timezone)->startOfDay() : $today->copy();
        $endDate = $startDate->copy()->addDays(9); // 10 days inclusive

        $dates = [];
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $dt) {
            $dates[] = $dt->copy();
        }

        $dateStrings = array_map(fn($d) => $d->toDateString(), $dates);

        $roomTypes = RoomType::with(['ratePlans'])->get();

        // Load inventory matrix [room_type_id][stay_date] => RoomInventory
        $inventoryRecords = RoomInventory::whereIn('room_type_id', $roomTypes->pluck('id'))
            ->whereIn('stay_date', $dateStrings)
            ->get();

        $inventoryMatrix = [];
        foreach ($inventoryRecords as $inv) {
            $inventoryMatrix[$inv->room_type_id][$inv->stay_date->toDateString()] = $inv;
        }

        // Load price matrix [rate_plan_id][stay_date] => RatePrice
        $ratePlanIds = RatePlan::pluck('id');
        $priceRecords = RatePrice::whereIn('rate_plan_id', $ratePlanIds)
            ->whereIn('stay_date', $dateStrings)
            ->get();

        $priceMatrix = [];
        foreach ($priceRecords as $rp) {
            $priceMatrix[$rp->rate_plan_id][$rp->stay_date->toDateString()] = $rp;
        }

        // Load sync state dots [kind][subject_id][stay_date][channel_code] => 'synced'|'pending'|'blocked'
        $syncStates = ChannelSyncState::with('channel')
            ->whereIn('stay_date', $dateStrings)
            ->get();

        $syncMatrix = [];
        foreach ($syncStates as $ss) {
            $channelCode = $ss->channel ? $ss->channel->code : 'ch';
            $dateStr = $ss->stay_date->toDateString();

            if ($ss->blocked_at !== null) {
                $status = 'blocked';
            } else {
                $currentVersion = 0;
                if ($ss->kind->value === 'availability') {
                    $inv = $inventoryMatrix[$ss->subject_id][$dateStr] ?? null;
                    $currentVersion = $inv ? (int) $inv->version : 0;
                } else {
                    $rp = $priceMatrix[$ss->subject_id][$dateStr] ?? null;
                    $currentVersion = $rp ? (int) $rp->version : 0;
                }

                $status = ($currentVersion > (int) $ss->pushed_version) ? 'pending' : 'synced';
            }

            $syncMatrix[$ss->kind->value][$ss->subject_id][$dateStr][$channelCode] = $status;
        }

        // Channels health
        $channels = Channel::all();
        $activeChannelsCount = $channels->where('status', ChannelStatus::Active)->count();
        $totalChannelsCount = $channels->count();

        // Critical alerts count
        $criticalAlertsCount = Alert::where('type', AlertType::Oversold)
            ->whereNull('resolved_at')
            ->count();

        // Calculate KPI Metrics:
        // Window occupancy: sum(booked) / sum(capacity) * 100
        $totalCapacity = $inventoryRecords->sum('capacity');
        $totalBooked = $inventoryRecords->sum('booked');
        $occupancyPercent = $totalCapacity > 0 ? round(($totalBooked / $totalCapacity) * 100, 1) : 0.0;

        // Average Daily Rate (ADR)
        $avgPrice = $priceRecords->avg('price') ?? 5500;
        $adrFormatted = '₹' . number_format((float) $avgPrice);

        return view('extranet.calendar', [
            'hotel' => $hotel,
            'today' => $today,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dates' => $dates,
            'roomTypes' => $roomTypes,
            'inventoryMatrix' => $inventoryMatrix,
            'priceMatrix' => $priceMatrix,
            'syncMatrix' => $syncMatrix,
            'channels' => $channels,
            'activeChannelsCount' => $activeChannelsCount,
            'totalChannelsCount' => $totalChannelsCount,
            'criticalAlertsCount' => $criticalAlertsCount,
            'occupancyPercent' => $occupancyPercent,
            'adrFormatted' => $adrFormatted,
        ]);
    }

    public function updateCell(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => 'nullable|integer|exists:room_types,id',
            'rate_plan_id' => 'nullable|integer|exists:rate_plans,id',
            'stay_date' => 'required|date_format:Y-m-d',
            'field' => 'required|string|in:capacity,stop_sell,price',
            'value' => 'required',
        ]);

        $stayDate = (string) $validated['stay_date'];

        if ($validated['field'] === 'capacity') {
            $roomTypeId = (int) $validated['room_type_id'];
            $capacity = (int) $validated['value'];
            try {
                $this->inventoryService->setCapacity($roomTypeId, [$stayDate], $capacity);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } elseif ($validated['field'] === 'stop_sell') {
            $roomTypeId = (int) $validated['room_type_id'];
            $stopSell = filter_var($validated['value'], FILTER_VALIDATE_BOOLEAN);
            $this->inventoryService->setStopSell($roomTypeId, [$stayDate], $stopSell);
        } elseif ($validated['field'] === 'price') {
            $ratePlanId = (int) $validated['rate_plan_id'];
            $price = (int) $validated['value'];
            $this->rateService->setPrice($ratePlanId, [$stayDate], $price);
        }

        return response()->json(['success' => true]);
    }

    public function bulkEdit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => 'nullable|integer|exists:room_types,id',
            'rate_plan_id' => 'nullable|integer|exists:rate_plans,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:1,7',
            'action' => 'required|string|in:set_capacity,set_price,set_stop_sell',
            'value' => 'required',
        ]);

        $weekdays = $validated['weekdays'] ?? [];
        $dates = $this->inventoryService->filterDates($validated['start_date'], $validated['end_date'], $weekdays);

        if (empty($dates)) {
            return response()->json(['error' => 'No dates selected matching filter.'], 422);
        }

        if ($validated['action'] === 'set_capacity') {
            $roomTypeId = (int) $validated['room_type_id'];
            try {
                $this->inventoryService->setCapacity($roomTypeId, $dates, (int) $validated['value']);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } elseif ($validated['action'] === 'set_stop_sell') {
            $roomTypeId = (int) $validated['room_type_id'];
            $this->inventoryService->setStopSell($roomTypeId, $dates, filter_var($validated['value'], FILTER_VALIDATE_BOOLEAN));
        } elseif ($validated['action'] === 'set_price') {
            $ratePlanId = (int) $validated['rate_plan_id'];
            $this->rateService->setPrice($ratePlanId, $dates, (int) $validated['value']);
        }

        return response()->json(['success' => true, 'updated_dates_count' => count($dates)]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $hotel = Hotel::first();
        $timezone = $hotel ? $hotel->timezone : 'Asia/Kolkata';
        $today = now()->timezone($timezone)->toDateString();

        $channels = Channel::all();

        // Level-triggered dirty count: version > pushed_version for availability and rates
        $dirtyAvail = DB::table('channel_sync_state as s')
            ->join('room_inventory as ri', function ($j) {
                $j->on('ri.room_type_id', '=', 's.subject_id')
                  ->on('ri.stay_date', '=', 's.stay_date');
            })
            ->where('s.kind', 'availability')
            ->whereNull('s.blocked_at')
            ->whereRaw('ri.version > s.pushed_version')
            ->where('s.stay_date', '>=', $today)
            ->count();

        $dirtyRates = DB::table('channel_sync_state as s')
            ->join('rate_prices as rp', function ($j) {
                $j->on('rp.rate_plan_id', '=', 's.subject_id')
                  ->on('rp.stay_date', '=', 's.stay_date');
            })
            ->where('s.kind', 'rate')
            ->whereNull('s.blocked_at')
            ->whereRaw('rp.version > s.pushed_version')
            ->where('s.stay_date', '>=', $today)
            ->count();

        $dirtyCount = $dirtyAvail + $dirtyRates;
        $blockedCount = ChannelSyncState::whereNotNull('blocked_at')->count();
        $openAlertsCount = Alert::whereNull('resolved_at')->count();

        return response()->json([
            'channels' => $channels->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'status' => $c->status->value,
                'circuit_open' => $c->circuitIsOpen(),
                'last_success_at' => $c->last_success_at?->diffForHumans(),
            ]),
            'dirty_count' => $dirtyCount,
            'blocked_count' => $blockedCount,
            'open_alerts_count' => $openAlertsCount,
        ]);
    }
}
