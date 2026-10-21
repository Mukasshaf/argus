<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Services\RateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class VerifySync extends Command
{
    protected $signature = 'staybridge:verify-sync';
    protected $description = 'Verifies sync convergence against simulator state';

    public function handle(): int
    {
        $today = now()->timezone('Asia/Kolkata')->toDateString();

        // 1. Check for pending jobs in queue
        $pendingJobs = DB::table('jobs')->count();
        if ($pendingJobs > 0) {
            $this->warn("not converged yet: {$pendingJobs} pending jobs in queue");
            return 2;
        }

        // 2. Check for dirty availability rows
        $hasDirtyAvail = DB::table('channel_sync_state as s')
            ->join('room_inventory as ri', function ($join) {
                $join->on('ri.room_type_id', '=', 's.subject_id')
                    ->on('ri.stay_date', '=', 's.stay_date');
            })
            ->where('s.kind', 'availability')
            ->where('s.stay_date', '>=', $today)
            ->whereColumn('ri.version', '>', 's.pushed_version')
            ->exists();

        // 3. Check for dirty rate rows
        $hasDirtyRates = DB::table('channel_sync_state as s')
            ->join('rate_prices as rp', function ($join) {
                $join->on('rp.rate_plan_id', '=', 's.subject_id')
                    ->on('rp.stay_date', '=', 's.stay_date');
            })
            ->where('s.kind', 'rate')
            ->where('s.stay_date', '>=', $today)
            ->whereColumn('rp.version', '>', 's.pushed_version')
            ->exists();

        // 4. Check for blocked/quarantined rows
        $hasBlocked = DB::table('channel_sync_state')
            ->whereNotNull('blocked_at')
            ->exists();

        if ($hasDirtyAvail || $hasDirtyRates || $hasBlocked) {
            $this->warn("not converged yet: dirty or blocked rows remain in channel_sync_state");
            return 2;
        }

        // 5. Compare with simulator state for active channels
        $channels = Channel::where('status', ChannelStatus::Active)->get();
        $simBaseUrl = rtrim(config('services.sim.base_url', 'http://localhost:8081'), '/');
        $mismatches = 0;

        foreach ($channels as $channel) {
            $url = "{$simBaseUrl}/sim/{$channel->code}/state";
            try {
                $res = Http::timeout(5)->get($url);
                if (!$res->successful()) {
                    $this->error("Failed to fetch state for {$channel->code} from {$url}: HTTP {$res->status()}");
                    $mismatches++;
                    continue;
                }

                $simState = $res->json();
                $simAvail = $simState['availability'] ?? [];
                $simRates = $simState['rates'] ?? [];

                // Compare availability
                $roomMappings = ChannelRoomMapping::where('channel_id', $channel->id)->get();
                foreach ($roomMappings as $mapping) {
                    $centralRows = DB::table('room_inventory')
                        ->where('room_type_id', $mapping->room_type_id)
                        ->where('stay_date', '>=', $today)
                        ->get();

                    foreach ($centralRows as $r) {
                        $expectedAvail = $r->stop_sell ? 0 : max(0, (int) $r->capacity - (int) $r->booked);
                        $simKey = "{$mapping->external_room_code}:{$r->stay_date}";
                        $actualAvail = $simAvail[$simKey] ?? $simAvail[$r->stay_date][$mapping->external_room_code] ?? null;

                        if ($actualAvail !== null && (int) $actualAvail !== $expectedAvail) {
                            $this->error("Mismatch on {$channel->code} Avail: Room {$mapping->external_room_code} on {$r->stay_date}: expected {$expectedAvail}, got {$actualAvail}");
                            $mismatches++;
                        }
                    }
                }

                // Compare rates
                $rateMappings = ChannelRateMapping::where('channel_id', $channel->id)->get();
                foreach ($rateMappings as $mapping) {
                    $centralRates = DB::table('rate_prices')
                        ->where('rate_plan_id', $mapping->rate_plan_id)
                        ->where('stay_date', '>=', $today)
                        ->get();

                    foreach ($centralRates as $r) {
                        $expectedPrice = RateService::applyMarkup((int) $r->price, $channel->markup_bps);
                        $simKey = "{$mapping->external_rate_code}:{$r->stay_date}";
                        $actualPrice = $simRates[$simKey] ?? $simRates[$r->stay_date][$mapping->external_rate_code] ?? null;

                        if ($actualPrice !== null && (int) $actualPrice !== $expectedPrice) {
                            $this->error("Mismatch on {$channel->code} Rate: Plan {$mapping->external_rate_code} on {$r->stay_date}: expected {$expectedPrice}, got {$actualPrice}");
                            $mismatches++;
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->error("Could not verify simulator state for {$channel->code}: {$e->getMessage()}");
                $mismatches++;
            }
        }

        if ($mismatches > 0) {
            $this->error("Verification failed: {$mismatches} state mismatches found.");
            return 1;
        }

        $this->info("Verification passed: all active channels converged and matched simulator state.");
        return 0;
    }
}
