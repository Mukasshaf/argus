<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'code',
        'name',
        'base_price',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RatePrice::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ChannelRateMapping::class);
    }
}
