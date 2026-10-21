<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncState extends Model
{
    use HasFactory;

    protected $table = 'channel_sync_state';

    protected $fillable = [
        'channel_id',
        'kind',
        'subject_id',
        'stay_date',
        'pushed_version',
        'attempts',
        'blocked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SyncKind::class,
            'stay_date' => 'date:Y-m-d',
            'pushed_version' => 'integer',
            'attempts' => 'integer',
            'blocked_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
