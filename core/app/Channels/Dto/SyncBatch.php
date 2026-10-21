<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class SyncBatch
{
    /**
     * @param 'availability'|'rate' $kind
     * @param list<AvailabilityRange>|list<RateRange> $ranges
     * @param array<int, int> $stateVersions state_id => version_seen
     */
    public function __construct(
        public int $channelId,
        public string $kind,
        public array $ranges,
        public array $stateVersions,
    ) {}
}
