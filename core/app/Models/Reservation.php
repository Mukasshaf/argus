<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'rate_plan_id',
        'channel_id',
        'external_id',
        'external_version',
        'status',
        'check_in',
        'check_out',
        'guest_name',
        'adults',
        'total_price',
        'currency',
        'inbound_event_id',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'external_version' => 'integer',
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'adults' => 'integer',
            'total_price' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function inboundEvent(): BelongsTo
    {
        return $this->belongsTo(InboundEvent::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class);
    }
}
