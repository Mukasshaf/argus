<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventory extends Model
{
    use HasFactory;

    protected $table = 'room_inventory';
    public $timestamps = false;

    protected $fillable = [
        'room_type_id',
        'stay_date',
        'capacity',
        'booked',
        'stop_sell',
        'version',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date:Y-m-d',
            'capacity' => 'integer',
            'booked' => 'integer',
            'available' => 'integer',
            'stop_sell' => 'boolean',
            'version' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
