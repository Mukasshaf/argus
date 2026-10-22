<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelProtocol: string
{
    case XML = 'xml';
    case JSON = 'json';
    case CSV = 'csv';
}
