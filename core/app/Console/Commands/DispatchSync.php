<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelStatus;
use App\Jobs\SyncChannelJob;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchSync extends Command
{
    protected $signature = 'argus:dispatch-sync';
    protected $description = 'Dispatches SyncChannelJob for channels with dirty rows or due for half-open check';

    public function handle(): int
    {
        $today = now()->timezone('Asia/Kolkata')->toDateString();

        $channels = Channel::where('status', '!=', ChannelStatus::Paused)->get();

        foreach ($channels as $channel) {
            // Check if circuit breaker is in half-open state or closed
            if ($channel->circuitIsOpen()) {
                continue;
            }

            // Check if there are dirty availability rows
            $hasDirtyAvail = DB::table('channel_sync_state as s')
                ->join('room_inventory as ri', function ($join) {
                    $join->on('ri.room_type_id', '=', 's.subject_id')
                        ->on('ri.stay_date', '=', 's.stay_date');
                })
                ->where('s.channel_id', $channel->id)
                ->where('s.kind', 'availability')
                ->whereNull('s.blocked_at')
                ->where('s.stay_date', '>=', $today)
                ->whereColumn('ri.version', '>', 's.pushed_version')
                ->exists();

            if ($hasDirtyAvail) {
                SyncChannelJob::dispatch($channel->id);
                $this->info("Dispatched sync for channel {$channel->code} (dirty availability)");
                continue;
            }

            // Check if there are dirty rate rows
            $hasDirtyRates = DB::table('channel_sync_state as s')
                ->join('rate_prices as rp', function ($join) {
                    $join->on('rp.rate_plan_id', '=', 's.subject_id')
                        ->on('rp.stay_date', '=', 's.stay_date');
                })
                ->where('s.channel_id', $channel->id)
                ->where('s.kind', 'rate')
                ->whereNull('s.blocked_at')
                ->where('s.stay_date', '>=', $today)
                ->whereColumn('rp.version', '>', 's.pushed_version')
                ->exists();

            if ($hasDirtyRates) {
                SyncChannelJob::dispatch($channel->id);
                $this->info("Dispatched sync for channel {$channel->code} (dirty rates)");
            }
        }

        return self::SUCCESS;
    }
}
