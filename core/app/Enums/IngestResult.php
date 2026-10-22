<?php

declare(strict_types=1);

namespace App\Enums;

enum IngestResult: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
}
