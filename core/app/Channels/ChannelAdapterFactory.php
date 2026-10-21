<?php

declare(strict_types=1);

namespace App\Channels;

use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Mela\MelaAdapter;
use App\Channels\Raahi\RaahiAdapter;
use App\Channels\Sarai\SaraiAdapter;
use App\Enums\ChannelProtocol;
use App\Models\Channel;
use InvalidArgumentException;

class ChannelAdapterFactory
{
    public function __construct(
        private RaahiAdapter $raahiAdapter,
        private SaraiAdapter $saraiAdapter,
        private MelaAdapter $melaAdapter,
    ) {}

    public function for(Channel $channel): ChannelAdapter
    {
        $protocol = $channel->protocol instanceof ChannelProtocol
            ? $channel->protocol
            : ChannelProtocol::tryFrom((string) $channel->protocol);

        return match ($protocol) {
            ChannelProtocol::XML => $this->raahiAdapter,
            ChannelProtocol::JSON => $this->saraiAdapter,
            ChannelProtocol::CSV => $this->melaAdapter,
            default => throw new PermanentChannelException("Unsupported channel protocol: {$channel->protocol->value}"),
        };
    }
}
