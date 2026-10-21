<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class RateRange
{
    /**
     * @param list<int> $stateIds
     */
    public function __construct(
        public int $ratePlanId,
        public string $externalRateCode,
        public string $startDate,
        public string $endDate,
        public int $price, // base price in INR
        public int $markedUpPrice, // price after markup in INR
        public array $stateIds,
        public int $versionSeen,
    ) {}
}
