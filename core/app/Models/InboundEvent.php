<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundEventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundEvent extends Model
{
    use HasFactory, Prunable;

    protected $fillable = [
        'channel_id',
        'external_event_id',
        'kind',
        'payload',
        'status',
        'error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InboundEventStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('received_at', '<=', now()->subDays(30));
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
