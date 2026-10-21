<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChannelProtocol;
use App\Enums\ChannelStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'protocol',
        'base_url',
        'credentials',
        'webhook_secret',
        'markup_bps',
        'supports_webhook',
        'supports_polling',
        'supports_realtime',
        'status',
        'consecutive_failures',
        'circuit_open_until',
        'last_success_at',
        'poll_cursor',
    ];

    protected function casts(): array
    {
        return [
            'protocol' => ChannelProtocol::class,
            'status' => ChannelStatus::class,
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'markup_bps' => 'integer',
            'supports_webhook' => 'boolean',
            'supports_polling' => 'boolean',
            'supports_realtime' => 'boolean',
            'consecutive_failures' => 'integer',
            'circuit_open_until' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function roomMappings(): HasMany
    {
        return $this->hasMany(ChannelRoomMapping::class);
    }

    public function rateMappings(): HasMany
    {
        return $this->hasMany(ChannelRateMapping::class);
    }

    public function syncStates(): HasMany
    {
        return $this->hasMany(ChannelSyncState::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function circuitIsOpen(): bool
    {
        if ($this->status === ChannelStatus::Paused) {
            return true;
        }

        if ($this->circuit_open_until === null) {
            return false;
        }

        // Half-open check: after 2 minutes, allow a test push through
        if (now()->greaterThanOrEqualTo($this->circuit_open_until)) {
            return false; // half-open: allows probe
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $this->update([
            'consecutive_failures' => 0,
            'circuit_open_until' => null,
            'last_success_at' => now(),
            'status' => ChannelStatus::Active,
        ]);
    }

    public function recordFailure(): void
    {
        $failures = $this->consecutive_failures + 1;
        $updates = [
            'consecutive_failures' => $failures,
        ];

        // Circuit breaker: open after 5 consecutive failures for 2 minutes
        if ($failures >= 5) {
            $updates['circuit_open_until'] = now()->addMinutes(2);
            $updates['status'] = ChannelStatus::Degraded;
        }

        $this->update($updates);
    }
}
