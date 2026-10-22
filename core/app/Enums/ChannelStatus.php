<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelStatus: string
{
    case Active = 'active';
    case Degraded = 'degraded';
    case Paused = 'paused';
}
