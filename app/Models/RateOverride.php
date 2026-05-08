<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateOverride extends Model
{
    /** @use HasFactory<\Database\Factories\RateOverrideFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rate_plan_id', 'room_category_id', 'date', 'override_price', 'is_closed', 'min_stay',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'override_price' => 'decimal:2',
            'is_closed' => 'boolean',
            'min_stay' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'rate_plan_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }
}
