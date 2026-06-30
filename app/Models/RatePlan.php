<?php

namespace App\Models;

use App\Enums\MealPlanEnum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
        'hotel_id',
        'parent_id',
        'modifier_percent',
        'name',
        'description',
        'meal_plan',
        'cancellation_free_days',
        'cancellation_penalty_percent',
        'prepayment_percent',
        'min_days_before_arrival',
        'max_days_before_arrival',
        'min_stay_days',
        'is_active',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isApplicable(Carbon $checkIn, Carbon $checkOut): bool
    {
        $nights = $checkIn->diffInDays($checkOut);
        $daysBeforeArrival = now()->diffInDays($checkIn);

        if ($this->is_active === false) {
            return false;
        }

        // Проверка минимального срока проживания
        if ($this->min_stay_days && $nights < $this->min_stay_days) {
            return false;
        }

        // Проверка минимального количества дней до заезда
        if ($this->min_days_before_arrival !== null && $daysBeforeArrival < $this->min_days_before_arrival) {
            return false;
        }

        // Проверка максимального количества дней до заезда
        if ($this->max_days_before_arrival !== null && $daysBeforeArrival > $this->max_days_before_arrival) {
            return false;
        }

        return true;
    }

    public function getPriceForDate(RoomCategory $category, Carbon $date): ?float
    {
        $sourcePlanId = $this->parent_id ?? $this->id;

        // Своё переопределение — наивысший приоритет
        if ($this->parent_id) {
            $childOverride = RateOverride::where('rate_plan_id', $this->id)
                ->where('room_category_id', $category->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($childOverride && $childOverride->is_closed) {
                return null;
            }

            if ($childOverride && $childOverride->override_price !== null) {
                return (float) $childOverride->override_price;
            }
        }

        // Переопределение родителя — цена с модификатором, закрытие игнорируем
        $parentOverride = RateOverride::where('rate_plan_id', $sourcePlanId)
            ->where('room_category_id', $category->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($parentOverride && $parentOverride->override_price !== null) {
            $price = (float) $parentOverride->override_price;
            if ($this->parent_id && $this->modifier_percent !== null) {
                $price = round($price * (1 + $this->modifier_percent / 100), 2);
            }
            return $price;
        }

        // Базовая цена родителя + модификатор
        $basePrice = RatePrice::where('rate_plan_id', $sourcePlanId)
            ->where('room_category_id', $category->id)
            ->where('date', $date->toDateString())
            ->value('price');

        if ($basePrice === null) {
            return null;
        }

        $price = (float) $basePrice;

        if ($this->parent_id && $this->modifier_percent !== null) {
            $price = round($price * (1 + $this->modifier_percent / 100), 2);
        }

        return $price;
    }

    public function isAvailableForCategory(RoomCategory $category, Carbon $checkIn, Carbon $checkOut): bool
    {
        // Сначала проверяем ограничения тарифа
        if (!$this->isApplicable($checkIn, $checkOut)) {
            return false;
        }

        // Проверяем каждую ночь периода
        $period = CarbonPeriod::create($checkIn, $checkOut->copy()->subDay());

        foreach ($period as $date) {
            $price = $this->getPriceForDate($category, $date);
            if ($price === null) {
                return false; // нет цены или дата закрыта
            }
        }

        return true;
    }
}
