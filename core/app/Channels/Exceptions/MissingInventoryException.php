<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

use App\Channels\Dto\ReservationEvent;
use Throwable;

class MissingInventoryException extends PermanentChannelException
{
    public function __construct(
        public ?ReservationEvent $event = null,
        string $message = 'Inventory records do not exist for the requested dates.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
