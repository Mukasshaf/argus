<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelRateMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'rate_plan_id',
        'external_rate_code',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public static function resolve(int $channelId, string $externalRateCode): ?self
    {
        return self::where('channel_id', $channelId)
            ->where('external_rate_code', $externalRateCode)
            ->first();
    }
}
