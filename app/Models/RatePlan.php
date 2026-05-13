<?php

namespace App\Models;

use App\Enums\MealPlanEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatePlan extends Model
{
    /** @use HasFactory<\Database\Factories\RatePlanFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'parent_id', 'modifier_percent', 'name', 'description', 'meal_plan', 'cancellation_free_days',
        'cancellation_penalty_percent', 'prepayment_percent', 'min_days_before_arrival', 'max_days_before_arrival',
        'min_stay_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'modifier_percent' => 'integer',
            'meal_plan' => MealPlanEnum::class,
            'cancellation_free_days' => 'integer',
            'cancellation_penalty_percent' => 'integer',
            'prepayment_percent' => 'integer',
            'min_days_before_arrival' => 'integer',
            'max_days_before_arrival' => 'integer',
            'min_stay_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RatePlan::class, 'parent_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RatePrice::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(RateOverride::class);
    }

    public function bookings(): HasMany {
        return $this->hasMany(Booking::class);
    }
}
