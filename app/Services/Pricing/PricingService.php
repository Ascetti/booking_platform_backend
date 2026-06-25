<?php

namespace App\Services\Pricing;

use App\Enums\ServiceTypeEnum;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RateOverride;
use App\Models\RatePrice;
use App\Models\RoomCategory;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PricingService
{
    const PLANNING_HORIZON_DAYS = 365;
    const DAY_MAP = [
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
        0 => 'sun',
    ];

    public function getBasePrices(RatePlan $ratePlan): Collection
    {
        $sourcePlan = $ratePlan->parent_id ? $ratePlan->parent()->first() : $ratePlan;

        $categories = RoomCategory::where('hotel_id', $sourcePlan->hotel_id)->get();

        $allPrices = RatePrice::where('rate_plan_id', $sourcePlan->id)
            ->where('date', '>=', today())
            ->get()
            ->groupBy('room_category_id');

        return $categories->map(function (RoomCategory $category) use ($allPrices, $ratePlan, $sourcePlan) {
            $categoryPrices = $allPrices->get($category->id, collect());

            $dayPrices = [];
            foreach (self::DAY_MAP as $dayNumber => $dayKey) {
                $record = $categoryPrices->first(
                    fn($price) => Carbon::parse($price->date)->dayOfWeek === $dayNumber
                );

                if ($record) {
                    $basePrice = (int) $record->price;

                    if ($ratePlan->parent_id && $ratePlan->modifier_percent !== null) {
                        $basePrice = (int) round($basePrice * (1 + $ratePlan->modifier_percent / 100));
                    }

                    $dayPrices[$dayKey] = $basePrice;
                } else {
                    $dayPrices[$dayKey] = null;
                }
            }

            return [
                'room_category_id' => $category->id,
                'name'             => $category->name,
                'day_prices'       => $dayPrices,
            ];
        })->values();
    }

    public function updateBasePrices(RatePlan $ratePlan, array $pricesData): Collection
    {
        return DB::transaction(function () use ($ratePlan, $pricesData) {
            $today = today();
            $horizon = $today->copy()->addDays(self::PLANNING_HORIZON_DAYS);

            $categoryIds = array_column($pricesData, 'room_category_id');

            RatePrice::where('rate_plan_id', $ratePlan->id)
                ->where('date', '>=', $today)
                ->whereIn('room_category_id', $categoryIds)
                ->delete();

            $period = CarbonPeriod::create($today, $horizon);

            $rows = [];

            foreach ($pricesData as $categoryData) {
                $categoryId = $categoryData['room_category_id'];
                $dayPrices = $categoryData['day_prices'];

                foreach ($period as $date) {
                    $dayKey = self::DAY_MAP[$date->dayOfWeek];
                    $price = $dayPrices[$dayKey] ?? null;
                    if ($price === null) {
                        continue;
                    }
                    $rows[] = [
                        'rate_plan_id'     => $ratePlan->id,
                        'room_category_id' => $categoryId,
                        'date'             => $date->toDateString(),
                        'price'            => $price,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                RatePrice::insert($chunk);
            }

            return $this->getBasePrices($ratePlan);
        });
    }

    /**
     * Создать или обновить переопределения на период.
     */
    public function updateOverrides(RatePlan $ratePlan, array $data): void
    {
        // Разворачиваем период в конкретные даты
        $period = CarbonPeriod::create($data['date_from'], $data['date_to']);

        $rows = [];
        foreach ($data['room_categories'] as $categoryId) {
            foreach ($period as $date) {
                $rows[] = [
                    'rate_plan_id'     => $ratePlan->id,
                    'room_category_id' => $categoryId,
                    'date'             => $date->toDateString(),
                    'override_price'   => $data['override_price'] ?? null,
                    'is_closed'        => $data['is_closed'] ?? false,
                ];
            }
        }

        // Если запись на эту дату уже есть — обновляем, если нет — создаём
        RateOverride::upsert(
            $rows,
            ['rate_plan_id', 'room_category_id', 'date'],
            ['override_price', 'is_closed']
        );
    }

    /**
     * Удалить переопределения за период для указанных категорий.
     */
    public function deleteOverrides(RatePlan $ratePlan, array $data): void
    {
        RateOverride::where('rate_plan_id', $ratePlan->id)
            ->whereBetween('date', [$data['date_from'], $data['date_to']])
            ->whereIn('room_category_id', $data['room_categories'])
            ->delete();
    }

    /**
     * Считает стоимость проживания за период.
     * Проходит по каждой ночи и суммирует цены.
     */
    public function calculateRoomPrice(RatePlan $ratePlan, RoomCategory $category, Carbon $checkIn, Carbon $checkOut): float
    {
        $total = 0.0;

        // Проходим по каждой ночи периода
        // subDay() потому что последний день это выезд — он не считается ночью
        // Например заезд 1 июня выезд 3 июня = 2 ночи (1 и 2 июня)
        $period = CarbonPeriod::create($checkIn, $checkOut->copy()->subDay());

        foreach ($period as $date) {
            $price = $ratePlan->getPriceForDate($category, $date);

            // Если на какую-то дату нет цены — бросаем исключение
            // Этого не должно случиться если перед созданием брони была проверка доступности
            if ($price === null) {
                throw new \LogicException(
                    "No price found for category {$category->id} on date {$date->toDateString()}"
                );
            }

            $total += $price;
        }

        return round($total, 2);
    }

    /**
     * Считает стоимость одной услуги с учётом типа цены.
     */
    public function calculateServicePrice(
        Service $service,
        int $quantity,
        int $nights
    ): float {
        $basePrice = (float) $service->price;

        $total = match ($service->price_type) {
            ServiceTypeEnum::PER_STAY             => $basePrice,
            ServiceTypeEnum::PER_SERVICE          => $basePrice * $quantity,
            ServiceTypeEnum::PER_NIGHT            => $basePrice * $quantity * $nights,
            ServiceTypeEnum::PER_PERSON           => $basePrice * $quantity,
            ServiceTypeEnum::PER_PERSON_PER_NIGHT => $basePrice * $quantity * $nights,
        };

        return round($total, 2);
    }

    /**
     * Считает итоговую стоимость бронирования.
     * Складывает стоимость проживания и всех услуг.
     */
    public function calculateTotalPrice(
        RatePlan $ratePlan,
        RoomCategory $category,
        Carbon $checkIn,
        Carbon $checkOut,
        array $services = [] // массив ['service' => Service, 'quantity' => int] 
    ): float {
        $nights = $checkIn->diffInDays($checkOut);

        // Стоимость проживания
        $roomPrice = $this->calculateRoomPrice($ratePlan, $category, $checkIn, $checkOut);

        // Стоимость услуг
        $servicesTotal = 0.0;
        foreach ($services as $item) {
            $servicesTotal += $this->calculateServicePrice(
                $item['service'],
                $item['quantity'],
                $nights,
            );
        }

        return round($roomPrice + $servicesTotal, 2);
    }

    /**
     * Получить сетку цен для тарифа за период.
     * Показывает базовые цены и переопределения вместе.
     */
    public function getRateGrid(RatePlan $ratePlan, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Для дочернего тарифа берём цены родителя
        $sourcePlan = $ratePlan->parent_id
            ? $ratePlan->parent()->first()
            : $ratePlan;

        // Берём все категории отеля
        $categories = RoomCategory::where('hotel_id', $ratePlan->hotel_id)->get();

        // Строим список дат периода
        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        // Загружаем базовые цены за период одним запросом
        $basePrices = RatePrice::where('rate_plan_id', $sourcePlan->id)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('room_category_id')
            ->map(fn($prices) => $prices->keyBy(fn($p) => $p->date->format('Y-m-d')));

        // Загружаем переопределения за период одним запросом
        $overrides = RateOverride::where('rate_plan_id', $sourcePlan->id)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('room_category_id')
            ->map(fn($items) => $items->keyBy(fn($i) => $i->date->format('Y-m-d')));

        // Модификатор для дочернего тарифа
        $modifier = ($ratePlan->parent_id && $ratePlan->modifier_percent !== null)
            ? (1 + $ratePlan->modifier_percent / 100)
            : 1;

        $result = [];

        foreach ($categories as $category) {
            $categoryBasePrices = $basePrices->get($category->id, collect());

            // dd([
            //     'raw_key'    => $categoryBasePrices->keys()->first(),
            //     'raw_value'  => $categoryBasePrices->first(),
            //     'date_field' => $categoryBasePrices->first()?->date,
            //     'date_type'  => gettype($categoryBasePrices->first()?->date),
            // ]);
            $categoryOverrides  = $overrides->get($category->id, collect());

            $days = [];

            foreach ($dates as $date) {
                $baseRecord     = $categoryBasePrices->get($date);
                $overrideRecord = $categoryOverrides->get($date);

                // Базовая цена с учётом модификатора дочернего тарифа
                $basePrice = $baseRecord
                    ? (float) round($baseRecord->price * $modifier, 2)
                    : null;

                // Цена переопределения с учётом модификатора
                $overridePrice = ($overrideRecord && $overrideRecord->override_price !== null)
                    ? (float) round($overrideRecord->override_price * $modifier, 2)
                    : null;

                $isClosed   = $overrideRecord ? (bool) $overrideRecord->is_closed : false;
                $isOverride = $overrideRecord !== null;

                // Итоговая цена — переопределение приоритетнее базовой
                $effectivePrice = $overridePrice ?? $basePrice;

                $days[] = [
                    'date'           => $date,
                    'base_price'     => $basePrice,
                    'override_price' => $overridePrice,
                    'effective_price' => $effectivePrice,
                    'is_override'    => $isOverride,
                    'is_closed'      => $isClosed,
                ];
            }

            $result[] = [
                'room_category_id' => $category->id,
                'name'             => $category->name,
                'days'             => $days,
            ];
        }

        return $result;
    }

    public function getCalendarPrices(Hotel $hotel, Carbon $dateFrom, Carbon $dateTo): array
    {
        $ratePlans  = RatePlan::where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->get();

        $categories = RoomCategory::where('hotel_id', $hotel->id)->get();

        $ratePlanIds  = $ratePlans->pluck('id');
        $categoryIds  = $categories->pluck('id');

        // Загружаем все базовые цены за период одним запросом
        $allPrices = RatePrice::whereIn('rate_plan_id', $ratePlanIds)
            ->whereIn('room_category_id', $categoryIds)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(fn($p) => $p->date->format('Y-m-d'))
            ->map(fn($byDate) => $byDate->groupBy('rate_plan_id')
                ->map(fn($byPlan) => $byPlan->keyBy('room_category_id')));

        // Загружаем все переопределения за период одним запросом
        $allOverrides = RateOverride::whereIn('rate_plan_id', $ratePlanIds)
            ->whereIn('room_category_id', $categoryIds)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(fn($o) => $o->date->format('Y-m-d'))
            ->map(fn($byDate) => $byDate->groupBy('rate_plan_id')
                ->map(fn($byPlan) => $byPlan->keyBy('room_category_id')));

        $result = [];
        $period = CarbonPeriod::create($dateFrom, $dateTo);

        foreach ($period as $date) {
            $dateStr  = $date->toDateString();
            $minPrice = null;

            $datePrices    = $allPrices->get($dateStr, collect());
            $dateOverrides = $allOverrides->get($dateStr, collect());

            foreach ($ratePlans as $plan) {
                // Для дочернего тарифа берём цены родителя
                $sourcePlanId = $plan->parent_id ?? $plan->id;

                foreach ($categories as $category) {
                    // Проверяем переопределение
                    $override = $dateOverrides->get($sourcePlanId)?->get($category->id);

                    if ($override && $override->is_closed) {
                        continue;
                    }

                    if ($override && $override->override_price !== null) {
                        $price = (float) $override->override_price;
                    } else {
                        $basePrice = $datePrices->get($sourcePlanId)?->get($category->id);
                        if (!$basePrice) {
                            continue;
                        }
                        $price = (float) $basePrice->price;
                    }

                    // Применяем модификатор дочернего тарифа
                    if ($plan->parent_id && $plan->modifier_percent !== null) {
                        $price = round($price * (1 + $plan->modifier_percent / 100), 2);
                    }

                    if ($minPrice === null || $price < $minPrice) {
                        $minPrice = $price;
                    }
                }
            }

            $result[$dateStr] = $minPrice;
        }

        return $result;
    }
}
