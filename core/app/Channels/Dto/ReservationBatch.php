<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class ReservationBatch
{
    /**
     * @param list<ReservationEvent> $events
     */
    public function __construct(
        public array $events,
        public ?string $nextCursor,
    ) {}
}
