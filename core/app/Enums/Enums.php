<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelProtocol: string
{
    case XML = 'xml';
    case JSON = 'json';
    case CSV = 'csv';
}

enum ChannelStatus: string
{
    case Active = 'active';
    case Degraded = 'degraded';
    case Paused = 'paused';
}

enum SyncKind: string
{
    case Availability = 'availability';
    case Rate = 'rate';
}

enum SyncOutcome: string
{
    case Ok = 'ok';
    case TransientFail = 'transient_fail';
    case PermanentFail = 'permanent_fail';
}

enum SyncDirection: string
{
    case Out = 'out';
    case In = 'in';
}

enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}

enum InboundEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}

enum AlertType: string
{
    case Oversold = 'oversold';
    case ChannelBlocked = 'channel_blocked';
    case UnmappedCode = 'unmapped_code';
    case Drift = 'drift';
}

enum IngestResult: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
}
