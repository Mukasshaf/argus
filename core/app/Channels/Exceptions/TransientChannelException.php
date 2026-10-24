<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

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
