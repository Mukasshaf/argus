<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertType: string
{
    case Oversold = 'oversold';
    case ChannelBlocked = 'channel_blocked';
    case UnmappedCode = 'unmapped_code';
    case Drift = 'drift';
}
