<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'hotel_id',
        'payload',
        'resolved_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'payload' => 'array',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public static function oversold(int $hotelId, int $roomTypeId, string $stayDate, int $available): self
    {
        // Check if an open oversold alert already exists for this room_type and stay_date
        $existing = self::where('type', AlertType::Oversold)
            ->where('hotel_id', $hotelId)
            ->whereNull('resolved_at')
            ->whereJsonContains('payload->room_type_id', $roomTypeId)
            ->whereJsonContains('payload->stay_date', $stayDate)
            ->first();

        if ($existing) {
            $payload = $existing->payload;
            $payload['available'] = $available;
            $existing->update(['payload' => $payload]);
            return $existing;
        }

        return self::create([
            'type' => AlertType::Oversold,
            'hotel_id' => $hotelId,
            'payload' => [
                'room_type_id' => $roomTypeId,
                'stay_date' => $stayDate,
                'available' => $available,
                'message' => "Room type {$roomTypeId} oversold on {$stayDate} (available: {$available})",
            ],
        ]);
    }

    public static function channelBlocked(Channel $channel, Throwable $e): self
    {
        return self::create([
            'type' => AlertType::ChannelBlocked,
            'hotel_id' => null,
            'payload' => [
                'channel_id' => $channel->id,
                'channel_code' => $channel->code,
                'error' => $e->getMessage(),
            ],
        ]);
    }

    public static function unmappedCode(int $channelId, string $kind, string $code): self
    {
        return self::create([
            'type' => AlertType::UnmappedCode,
            'hotel_id' => null,
            'payload' => [
                'channel_id' => $channelId,
                'kind' => $kind,
                'code' => $code,
            ],
        ]);
    }

    public static function drift(int $roomTypeId, string $stayDate, int $booked, int $nightsCount): self
    {
        return self::create([
            'type' => AlertType::Drift,
            'hotel_id' => null,
            'payload' => [
                'room_type_id' => $roomTypeId,
                'stay_date' => $stayDate,
                'inventory_booked' => $booked,
                'reservation_nights_count' => $nightsCount,
            ],
        ]);
    }

    public static function resolveOversoldIfCured(int $roomTypeId, string $stayDate): void
    {
        $openAlerts = self::where('type', AlertType::Oversold)
            ->whereNull('resolved_at')
            ->whereJsonContains('payload->room_type_id', $roomTypeId)
            ->whereJsonContains('payload->stay_date', $stayDate)
            ->get();

        foreach ($openAlerts as $alert) {
            $alert->update(['resolved_at' => now()]);
        }
    }
}
