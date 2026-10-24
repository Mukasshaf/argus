<?php

declare(strict_types=1);

namespace App\Channels\Exceptions;

use Exception;

class PermanentChannelException extends Exception
{
    // Permanent failure - should quarantine rows and not auto-retry
}
