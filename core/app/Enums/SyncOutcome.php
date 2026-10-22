<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncOutcome: string
{
    case Ok = 'ok';
    case TransientFail = 'transient_fail';
    case PermanentFail = 'permanent_fail';
}
