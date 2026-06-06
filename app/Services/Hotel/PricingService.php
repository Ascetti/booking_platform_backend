<?php

namespace App\Services\Hotel;

use App\Models\RatePlan;
use App\Models\RateOverride;
use App\Models\RatePrice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PricingService
{
    private const HORIZON_DAYS = 365;

    public function updatePricing(RatePlan $ratePlan, array $data): void
    {
        $period = CarbonPeriod::create($data['start_date'], $data['end_date']);
        $daysOfWeek = $data['days_of_week'] ?? [];

        DB::transaction(function () use ($ratePlan, $period, $daysOfWeek, $data) {
            $toUpsert = [];

            foreach ($data['room_categories'] as $categoryId) {
                foreach ($period as $date) {
                    if (!empty($daysOfWeek) && !in_array(strtolower($date->englishDayOfWeek), $daysOfWeek)) {
                        continue;
                    }

                    $toUpsert[] = [
                        'rate_plan_id' => $ratePlan->id,
                        'room_category_id' => $categoryId,
                        'date' => $date->format('Y-m-d'),
                        'override_price' => $data['price'] ?? null,
                        'min_stay' => $data['min_stay'] ?? null,
                        'is_closed' => $data['is_closed'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($toUpsert) >= 500) {
                        $this->performOverrideUpsert($toUpsert);
                        $toUpsert = [];
                    }
                }
            }

            if (!empty($toUpsert)) {
                $this->performOverrideUpsert($toUpsert);
            }
        });
    }

    protected function performOverrideUpsert(array $rows): void
    {
        RateOverride::upsert(
            $rows,
            ['rate_plan_id', 'room_category_id', 'date'],
            ['override_price', 'min_stay', 'is_closed', 'updated_at']
        );
    }

    public function getPricingGrid(RatePlan $ratePlan, string $startDate, string $endDate): Collection
    {
        $baseRatePlanId = $ratePlan->parent_id ?? $ratePlan->id;

        return RatePrice::query()
            ->where('rate_plan_id', $baseRatePlanId)
            ->whereBetween('date', [$startDate, $endDate])
            ->leftJoin('rate_overrides', function ($join) use ($ratePlan) {
                $join->on('rate_prices.date', '=', 'rate_overrides.date')
                    ->on('rate_prices.room_category_id', '=', 'rate_overrides.room_category_id')
                    ->where('rate_overrides.rate_plan_id', '=', $ratePlan->id);
            })
            ->select([
                'rate_prices.date',
                'rate_prices.room_category_id',
                'rate_prices.price as base_price',
                'rate_overrides.override_price',
                'rate_overrides.min_stay',
                'rate_overrides.is_closed',
                DB::raw('COALESCE(rate_overrides.override_price, rate_prices.price) as raw_final_price')
            ])
            ->get()
            ->map(function ($item) use ($ratePlan) {
                // Формула: Цена * (1 + (модификатор / 100))
                // Например: 1000 * (1 + (-10 / 100)) = 900
                $finalPrice = $ratePlan->parent_id
                    ? $item->raw_final_price * (1 + ($ratePlan->modifier_percent / 100))
                    : $item->raw_final_price;

                $item->final_price = round($finalPrice, 2);
                $item->active_min_stay = $item->min_stay ?? $ratePlan->min_stay_days;
                $item->active_is_closed = $item->is_closed ?? false;
                $item->is_override_active = !is_null($item->override_price);

                return $item;
            });
    }


    public function generateFuturePrices(RatePlan $ratePlan, array $pricing, Carbon $from): void
    {
        if ($ratePlan->parent_id !== null) {
            return;
        }

        $from = $from->copy()->startOfDay();
        $to = $from->copy()->addDays(self::HORIZON_DAYS - 1);

        RatePrice::where('rate_plan_id', $ratePlan->id)
            ->where('date', '>=', $from)
            ->delete();
        $period = CarbonPeriod::create($from, $to);
        $rows = [];
        foreach ($pricing['categories'] as $category) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $day = strtolower($date->englishDayOfWeek);
                $rows[] = [
                    'rate_plan_id' => $ratePlan->id,
                    'room_category_id' => $category['room_category_id'],
                    'date' => $date->toDateString(),
                    'price' => $category['weekdays'][$day],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (count($rows) >= 1000) {
                    RatePrice::insert($rows);
                    $rows = [];
                }
            }
        }
        if (!empty($rows)) {
            RatePrice::insert($rows);
        }
    }
}
