<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class AvailabilityRange
{
    /**
     * @param list<int> $stateIds
     */
    public function __construct(
        public int $roomTypeId,
        public string $externalRoomCode,
        public string $startDate,
        public string $endDate,
        public int $available, // clamped max(0, available), or 0 if stop_sell
        public bool $stopSell,
        public array $stateIds,
        public int $versionSeen,
    ) {}
}
