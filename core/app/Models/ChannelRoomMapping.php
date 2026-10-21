<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelRoomMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'room_type_id',
        'external_room_code',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public static function resolve(int $channelId, string $externalRoomCode): ?self
    {
        return self::where('channel_id', $channelId)
            ->where('external_room_code', $externalRoomCode)
            ->first();
    }
}
