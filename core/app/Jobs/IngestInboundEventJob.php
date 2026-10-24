<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\ChannelAdapterFactory;
use App\Enums\InboundEventStatus;
use App\Models\Channel;
use App\Models\InboundEvent;
use App\Services\ReservationIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestInboundEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $inboundEventId)
    {
        $this->onQueue('argus_ingest');
    }

    public function handle(
        ChannelAdapterFactory $factory,
        ReservationIngestService $ingestService
    ): void {
        $event = InboundEvent::find($this->inboundEventId);
        if (!$event || $event->status === InboundEventStatus::Processed) {
            return;
        }

        $channel = Channel::find($event->channel_id);
        if (!$channel) {
            $event->update([
                'status' => InboundEventStatus::Failed,
                'error' => "Channel {$event->channel_id} not found.",
            ]);
            return;
        }

        try {
            $adapter = $factory->for($channel);
            $parsedEvents = $adapter->parseInbound($channel, $event->payload, []);

            if (empty($parsedEvents)) {
                $event->update([
                    'status' => InboundEventStatus::Ignored,
                    'processed_at' => now(),
                ]);
                return;
            }

            foreach ($parsedEvents as $reservationEvent) {
                $ingestService->apply($reservationEvent);
            }

            $event->update([
                'status' => InboundEventStatus::Processed,
                'error' => null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $event->update([
                'status' => InboundEventStatus::Failed,
                'error' => substr($e->getMessage(), 0, 1000),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::channel('stderr')->error("IngestInboundEventJob failed for event {$this->inboundEventId}", [
            'inbound_event_id' => $this->inboundEventId,
            'exception' => $e->getMessage(),
        ]);
    }
}
