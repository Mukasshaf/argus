<?php

declare(strict_types=1);

namespace App\Channels;

use App\Channels\Dto\AvailabilityRange;
use App\Channels\Dto\Capabilities;
use App\Channels\Dto\RateRange;
use App\Channels\Dto\ReservationBatch;
use App\Channels\Dto\ReservationEvent;
use App\Models\Channel;

interface ChannelAdapter
{
    public function capabilities(): Capabilities;

    /**
     * @param list<AvailabilityRange> $ranges absolute values, clamped, mapped to external codes
     */
    public function pushAvailability(Channel $c, array $ranges, string $correlationId): void;

    /**
     * @param list<RateRange> $ranges channel markup already applied
     */
    public function pushRates(Channel $c, array $ranges, string $correlationId): void;

    /**
     * Pulls reservations for polling channels (events plus next cursor)
     */
    public function pullReservations(Channel $c, ?string $cursor): ReservationBatch;

    /**
     * Parses raw webhook or message body into reservation events
     *
     * @return list<ReservationEvent>
     */
    public function parseInbound(Channel $c, string $rawBody, array $headers): array;
}
