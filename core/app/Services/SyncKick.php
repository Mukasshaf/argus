<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChannelStatus;
use App\Jobs\SyncChannelJob;
use App\Models\Channel;

class SyncKick
{
    public static function allChannels(): void
    {
        $channels = Channel::where('status', '!=', ChannelStatus::Paused)->get();

        foreach ($channels as $channel) {
            SyncChannelJob::dispatch($channel->id)
                ->delay(now()->addSeconds(3))
                ->afterCommit();
        }
    }

    public static function channel(int $channelId): void
    {
        SyncChannelJob::dispatch($channelId)
            ->delay(now()->addSeconds(3))
            ->afterCommit();
    }
}
