<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\ChannelAdapterFactory;
use App\Enums\ChannelStatus;
use App\Enums\InboundEventStatus;
use App\Jobs\IngestInboundEventJob;
use App\Models\Channel;
use App\Models\InboundEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PollChannels extends Command
{
    protected $signature = 'argus:poll-channels';
    protected $description = 'Pulls reservations for polling channels and dispatches ingestion jobs';

    public function handle(ChannelAdapterFactory $factory): int
    {
        $channels = Channel::where('supports_polling', true)
            ->where('status', '!=', ChannelStatus::Paused)
            ->get();

        foreach ($channels as $channel) {
            $this->info("Polling channel {$channel->code}...");
            try {
                $adapter = $factory->for($channel);
                $batch = $adapter->pullReservations($channel, $channel->poll_cursor);

                DB::transaction(function () use ($channel, $batch) {
                    foreach ($batch->events as $evt) {
                        $inbound = InboundEvent::firstOrCreate(
                            [
                                'channel_id' => $channel->id,
                                'external_event_id' => $evt->externalEventId,
                            ],
                            [
                                'kind' => 'reservation',
                                'payload' => json_encode($evt),
                                'status' => InboundEventStatus::Received,
                                'received_at' => now(),
                            ]
                        );

                        if ($inbound->wasRecentlyCreated) {
                            IngestInboundEventJob::dispatch($inbound->id);
                        }
                    }

                    // Cursor advances only after events are persisted (Section 7.8)
                    if ($batch->nextCursor !== null) {
                        $channel->update(['poll_cursor' => $batch->nextCursor]);
                    }
                });

                $channel->recordSuccess();
                $this->info("Successfully polled {$channel->code}: " . count($batch->events) . " events.");
            } catch (Throwable $e) {
                $channel->recordFailure();
                $this->error("Error polling {$channel->code}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
