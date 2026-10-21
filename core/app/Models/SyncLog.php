<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncDirection;
use App\Enums\SyncOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory, Prunable;

    public $timestamps = false;

    protected $fillable = [
        'correlation_id',
        'channel_id',
        'direction',
        'operation',
        'request_body',
        'response_status',
        'response_body',
        'duration_ms',
        'attempt',
        'outcome',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => SyncDirection::class,
            'outcome' => SyncOutcome::class,
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'attempt' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(14));
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
