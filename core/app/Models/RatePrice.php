<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_plan_id',
        'stay_date',
        'price',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date:Y-m-d',
            'price' => 'integer',
            'version' => 'integer',
        ];
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }
}
