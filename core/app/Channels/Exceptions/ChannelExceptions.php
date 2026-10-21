<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

use App\Channels\Dto\ReservationEvent;
use Exception;
use Throwable;

class TransientChannelException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        public ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

class PermanentChannelException extends Exception
{
    // Permanent failure - should quarantine rows and not auto-retry
}

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

class SoldOutException extends Exception
{
    public function __construct(string $message = 'Requested room is sold out for one or more dates.')
    {
        parent::__construct($message, 409);
    }
}

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
