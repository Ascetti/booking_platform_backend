<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatePrice extends Model
{
    /** @use HasFactory<\Database\Factories\RatePriceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rate_plan_id', 'room_category_id', 'date', 'price',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
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
