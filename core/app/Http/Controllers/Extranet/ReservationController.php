<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Channels\Dto\ReservationEvent;
use App\Enums\InboundEventStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\IngestInboundEventJob;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\InboundEvent;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\ReservationIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $hotel = Hotel::first();
        $tab = $request->query('tab', 'all'); // all, confirmed, oversold, failed, cancelled
        $search = $request->query('search');
        $channelId = $request->query('channel_id');

        $query = Reservation::with(['roomType', 'ratePlan', 'channel'])
            ->orderBy('received_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%");
            });
        }

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        // Handle tabs
        $oversoldNights = [];
        $failedEvents = [];

        if ($tab === 'confirmed') {
            $query->where('status', ReservationStatus::Confirmed);
        } elseif ($tab === 'cancelled') {
            $query->where('status', ReservationStatus::Cancelled);
        } elseif ($tab === 'oversold') {
            // D4: Oversold nights view lists dates with available < 0 joined to holders ordered by received_at
            $oversoldInventories = RoomInventory::with('roomType')
                ->where('available', '<', 0)
                ->orderBy('stay_date')
                ->get();

            foreach ($oversoldInventories as $inv) {
                $holders = DB::table('reservations as r')
                    ->join('reservation_nights as rn', 'rn.reservation_id', '=', 'r.id')
                    ->where('rn.room_type_id', $inv->room_type_id)
                    ->where('rn.stay_date', $inv->stay_date->toDateString())
                    ->where('r.status', ReservationStatus::Confirmed->value)
                    ->orderBy('r.received_at', 'asc')
                    ->select('r.*')
                    ->get();

                $oversoldNights[] = [
                    'inventory' => $inv,
                    'deficit' => abs($inv->available),
                    'holders' => $holders,
                ];
            }
        } elseif ($tab === 'failed') {
            $failedEvents = InboundEvent::with('channel')
                ->where('status', InboundEventStatus::Failed)
                ->orderBy('received_at', 'desc')
                ->paginate(15);
        }

        $reservations = $query->paginate(15)->withQueryString();

        // Vital Metric Counts
        $totalReservationsCount = Reservation::count();
        $confirmedCount = Reservation::where('status', ReservationStatus::Confirmed)->count();
        $cancelledCount = Reservation::where('status', ReservationStatus::Cancelled)->count();
        $criticalOversoldCount = RoomInventory::where('available', '<', 0)->count();
        $failedInboundCount = InboundEvent::where('status', InboundEventStatus::Failed)->count();

        $channels = Channel::all();
        $roomTypes = RoomType::all();
        $ratePlans = RatePlan::all();

        return view('extranet.reservations', [
            'hotel' => $hotel,
            'tab' => $tab,
            'search' => $search,
            'channelId' => $channelId,
            'reservations' => $reservations,
            'oversoldNights' => $oversoldNights,
            'failedEvents' => $failedEvents,
            'totalReservationsCount' => $totalReservationsCount,
            'confirmedCount' => $confirmedCount,
            'cancelledCount' => $cancelledCount,
            'criticalOversoldCount' => $criticalOversoldCount,
            'failedInboundCount' => $failedInboundCount,
            'channels' => $channels,
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'reservations_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Booking ID', 'Channel', 'Guest Name', 'Room Type', 'Rate Plan', 'Check In', 'Check Out', 'Adults', 'Total (INR)', 'Status', 'Received At']);

            Reservation::with(['channel', 'roomType', 'ratePlan'])
                ->orderBy('received_at', 'desc')
                ->chunk(500, function ($reservations) use ($handle) {
                    foreach ($reservations as $r) {
                        fputcsv($handle, [
                            $r->external_id,
                            $r->channel?->name ?? 'N/A',
                            $r->guest_name,
                            $r->roomType?->name ?? 'N/A',
                            $r->ratePlan?->name ?? 'N/A',
                            $r->check_in?->toDateString(),
                            $r->check_out?->toDateString(),
                            $r->adults,
                            $r->total_price,
                            $r->status->value,
                            $r->received_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function manualBooking(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guest_name' => 'required|string|max:255',
            'channel_id' => 'required|exists:channels,id',
            'room_type_id' => 'required|exists:room_types,id',
            'rate_plan_id' => 'required|exists:rate_plans,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:10',
            'total_price' => 'required|numeric|min:0',
        ]);

        $channel = Channel::findOrFail($validated['channel_id']);
        $roomMapping = ChannelRoomMapping::where('channel_id', $channel->id)
            ->where('room_type_id', $validated['room_type_id'])
            ->first();
        $rateMapping = ChannelRateMapping::where('channel_id', $channel->id)
            ->where('rate_plan_id', $validated['rate_plan_id'])
            ->first();

        if (!$roomMapping || !$rateMapping) {
            return back()->withErrors(['manual' => 'Selected room type or rate plan is not mapped for this channel.']);
        }

        $event = new ReservationEvent(
            channelId: $channel->id,
            externalId: 'MAN-' . strtoupper(bin2hex(random_bytes(4))),
            externalVersion: 1,
            type: 'upsert',
            roomCode: $roomMapping->external_room_code,
            rateCode: $rateMapping->external_rate_code,
            checkIn: $validated['check_in'],
            checkOut: $validated['check_out'],
            guestName: $validated['guest_name'],
            adults: (int) $validated['adults'],
            totalPrice: (int) $validated['total_price'],
            currency: 'INR',
            externalEventId: 'MAN-EVT-' . uniqid(),
        );

        try {
            app(ReservationIngestService::class)->apply($event);

            return back()->with('success', 'Manual reservation created and inventory updated successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['manual' => 'Failed to create reservation: ' . $e->getMessage()]);
        }
    }

    public function reprocessEvent(int $id): RedirectResponse
    {
        $event = InboundEvent::findOrFail($id);
        $event->update([
            'status' => InboundEventStatus::Received,
            'error' => null,
        ]);

        IngestInboundEventJob::dispatch($event->id);

        return back()->with('success', "Event #{$id} queued for reprocessing.");
    }
}
