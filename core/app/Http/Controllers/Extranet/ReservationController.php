<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\InboundEventStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\IngestInboundEventJob;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\InboundEvent;
use App\Models\Reservation;
use App\Models\RoomInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        $reservations = $query->paginate(15);

        // Vital Metric Counts
        $totalReservationsCount = Reservation::count();
        $confirmedCount = Reservation::where('status', ReservationStatus::Confirmed)->count();
        $cancelledCount = Reservation::where('status', ReservationStatus::Cancelled)->count();
        $criticalOversoldCount = RoomInventory::where('available', '<', 0)->count();
        $failedInboundCount = InboundEvent::where('status', InboundEventStatus::Failed)->count();

        $channels = Channel::all();

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
        ]);
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
