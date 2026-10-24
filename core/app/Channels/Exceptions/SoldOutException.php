<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

use Exception;

class SoldOutException extends Exception
{
    public function __construct(string $message = 'Requested room is sold out for one or more dates.')
    {
        parent::__construct($message, 409);
    }
}
