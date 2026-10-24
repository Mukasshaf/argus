<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\ChannelAdapterFactory;
use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Exceptions\TransientChannelException;
use App\Models\Alert;
use App\Models\Channel;
use App\Services\SyncPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncChannelJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;
    public int $timeout = 90;
    public int $uniqueFor = 300; // a crashed worker cannot hold the lock forever

    public function __construct(public int $channelId)
    {
        $this->onQueue('argus_sync');
    }

    public function uniqueId(): string
    {
        return (string) $this->channelId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        // Exponential backoff with small random jitter (0-3s)
        return [
            10 + random_int(0, 3),
            30 + random_int(0, 5),
            90 + random_int(0, 10),
            270 + random_int(0, 15),
            600,
        ];
    }

    public function handle(ChannelAdapterFactory $f, SyncPlanner $planner): void
    {
        $channel = Channel::find($this->channelId);
        if (!$channel) {
            return;
        }

        if ($channel->circuitIsOpen()) {
            return;
        }

        $adapter = $f->for($channel);
        $corr = (string) Str::uuid();
        $deadline = now()->addSeconds(60); // time budget below job timeout 90s

        while (now()->lt($deadline) && ($batch = $planner->nextBatch($channel))) {
            try {
                if ($batch->kind === 'availability') {
                    $adapter->pushAvailability($channel, $batch->ranges, $corr);
                } else {
                    $adapter->pushRates($channel, $batch->ranges, $corr);
                }

                $planner->markPushed($channel, $batch);
                $channel->recordSuccess();
            } catch (TransientChannelException $e) {
                $channel->recordFailure();
                $delay = $e->retryAfter ?? $this->backoff()[min($this->attempts() - 1, 4)];
                $this->release($delay);
                return;
            } catch (PermanentChannelException $e) {
                $planner->quarantine($channel, $batch, $e->getMessage());
                Alert::channelBlocked($channel, $e);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::channel('stderr')->error("SyncChannelJob failed for channel {$this->channelId}", [
            'channel_id' => $this->channelId,
            'exception' => $e->getMessage(),
        ]);
    }
}
