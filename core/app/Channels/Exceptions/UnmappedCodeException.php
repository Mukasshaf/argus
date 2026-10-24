<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

use App\Channels\Dto\ReservationEvent;
use Throwable;

class UnmappedCodeException extends PermanentChannelException
{
    public function __construct(
        public ?ReservationEvent $event = null,
        string $message = 'Unmapped room or rate code received from channel.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
