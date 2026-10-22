<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncKind: string
{
    case Availability = 'availability';
    case Rate = 'rate';
}
