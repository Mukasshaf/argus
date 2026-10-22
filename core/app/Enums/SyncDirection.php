<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncDirection: string
{
    case Out = 'out';
    case In = 'in';
}
