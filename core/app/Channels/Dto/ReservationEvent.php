<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class ReservationEvent
{
    public function __construct(
        public int $channelId,
        public string $externalId,
        public int $externalVersion,
        public string $type, // 'upsert' or 'cancel'
        public string $roomCode,
        public string $rateCode,
        public string $checkIn,
        public string $checkOut,
        public string $guestName,
        public int $adults,
        public int $totalPrice,
        public string $currency,
        public string $externalEventId,
    ) {}
}
