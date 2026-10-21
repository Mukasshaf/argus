<?php

declare(strict_types=1);

namespace App\Channels\Dto;

readonly class Capabilities
{
    public function __construct(
        public bool $supportsPushAvailability = true,
        public bool $supportsPushRates = true,
        public bool $supportsPullReservations = false,
        public bool $supportsWebhooks = false,
        public bool $supportsRealtime = false,
    ) {}
}
