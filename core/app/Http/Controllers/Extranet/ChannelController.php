<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\ChannelStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncChannelJob;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncState;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\SyncKick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelController extends Controller
{
    public function index(): View
    {
        $hotel = $this->getActiveHotel() ?? Hotel::first();
        $channels = Channel::with(['roomMappings.roomType', 'rateMappings.ratePlan'])->get();

        $activeChannelsCount = $channels->where('status', ChannelStatus::Active)->count();
        $totalMappingsCount = ChannelRoomMapping::count() + ChannelRateMapping::count();
        $expectedMappingsCount = 9; // 3 channels * 3 room types (approx)

        $lastSuccessAt = $channels->max('last_success_at');

        // MTD booking volume per channel
        $mtdStats = [];
        $startOfMonth = now()->startOfMonth();
        foreach ($channels as $c) {
            $sum = Reservation::where('channel_id', $c->id)
                ->where('created_at', '>=', $startOfMonth)
                ->sum('total_price');
            $count = Reservation::where('channel_id', $c->id)
                ->where('created_at', '>=', $startOfMonth)
                ->count();
            $quarantinedCount = ChannelSyncState::where('channel_id', $c->id)
                ->whereNotNull('blocked_at')
                ->count();

            $mtdStats[$c->id] = [
                'volume' => $sum,
                'count' => $count,
                'quarantined' => $quarantinedCount,
            ];
        }

        $roomTypes = \App\Models\RoomType::with(['ratePlans', 'mappings.channel'])->get();

        return view('extranet.channels', [
            'hotel' => $hotel,
            'channels' => $channels,
            'roomTypes' => $roomTypes,
            'activeChannelsCount' => $activeChannelsCount,
            'totalMappingsCount' => $totalMappingsCount,
            'expectedMappingsCount' => $expectedMappingsCount,
            'lastSuccessAt' => $lastSuccessAt,
            'mtdStats' => $mtdStats,
        ]);
    }

    public function syncAll(): RedirectResponse
    {
        Channel::query()->update([
            'consecutive_failures' => 0,
            'circuit_open_until' => null,
            'status' => ChannelStatus::Active,
        ]);
        ChannelSyncState::whereNotNull('blocked_at')->update([
            'blocked_at' => null,
            'attempts' => 0,
            'last_error' => null,
            'updated_at' => now(),
        ]);
        SyncKick::allChannels();

        return back()->with('success', 'Reset circuits and triggered full synchronization for all active channels.');
    }

    public function forceSync(int $id): RedirectResponse
    {
        $channel = Channel::findOrFail($id);
        $channel->update([
            'consecutive_failures' => 0,
            'circuit_open_until' => null,
            'status' => ChannelStatus::Active,
        ]);
        SyncChannelJob::dispatch($channel->id);

        return back()->with('success', "Triggered full synchronization for {$channel->name}.");
    }

    public function togglePause(int $id): RedirectResponse
    {
        $channel = Channel::findOrFail($id);
        if ($channel->status === ChannelStatus::Paused) {
            $channel->update(['status' => ChannelStatus::Active]);
            $msg = "Channel {$channel->name} resumed.";
        } else {
            $channel->update(['status' => ChannelStatus::Paused]);
            $msg = "Channel {$channel->name} paused.";
        }

        return back()->with('success', $msg);
    }

    public function unblock(int $id): RedirectResponse
    {
        $channel = Channel::findOrFail($id);
        $channel->update([
            'consecutive_failures' => 0,
            'circuit_open_until' => null,
            'status' => ChannelStatus::Active,
        ]);
        $count = ChannelSyncState::where('channel_id', $channel->id)
            ->whereNotNull('blocked_at')
            ->update([
                'blocked_at' => null,
                'attempts' => 0,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        SyncChannelJob::dispatch($channel->id);

        return back()->with('success', "Unblocked {$count} quarantined rows for {$channel->name}. Sync re-queued.");
    }
}
