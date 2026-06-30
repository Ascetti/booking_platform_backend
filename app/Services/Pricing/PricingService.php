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
        ServiceTypeEnum $priceType,
        float $unitPrice,       // price_at_booking или $service->price
        int $quantity,
        int $nights
    ): float {
        $total = match ($priceType) {
            ServiceTypeEnum::PER_STAY             => $unitPrice,
            ServiceTypeEnum::PER_SERVICE          => $unitPrice * $quantity,
            ServiceTypeEnum::PER_NIGHT            => $unitPrice * $quantity * $nights,
            ServiceTypeEnum::PER_PERSON           => $unitPrice * $quantity,
            ServiceTypeEnum::PER_PERSON_PER_NIGHT => $unitPrice * $quantity * $nights,
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
                $item['service']->price_type,
                (float) $item['service']->price,
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
        $sourcePlan = $ratePlan->parent_id
            ? $ratePlan->parent()->first()
            : $ratePlan;

        $categories = RoomCategory::where('hotel_id', $ratePlan->hotel_id)->get();

        $dates = [];
        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $date) {
            $dates[] = $date->toDateString();
        }

        $modifier = ($ratePlan->parent_id && $ratePlan->modifier_percent !== null)
            ? (1 + $ratePlan->modifier_percent / 100)
            : 1;

        // Базовые цены родителя
        $basePrices = RatePrice::where('rate_plan_id', $sourcePlan->id)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('room_category_id')
            ->map(fn($prices) => $prices->keyBy(fn($p) => $p->date->format('Y-m-d')));

        // Переопределения родителя — для расчёта цены
        $parentOverrides = RateOverride::where('rate_plan_id', $sourcePlan->id)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('room_category_id')
            ->map(fn($items) => $items->keyBy(fn($i) => $i->date->format('Y-m-d')));

        // Свои переопределения тарифа — для отображения флагов
        $ownOverrides = collect();
        if ($ratePlan->parent_id) {
            $ownOverrides = RateOverride::where('rate_plan_id', $ratePlan->id)
                ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->get()
                ->groupBy('room_category_id')
                ->map(fn($items) => $items->keyBy(fn($i) => $i->date->format('Y-m-d')));
        } else {
            $ownOverrides = $parentOverrides;
        }

        $result = [];

        foreach ($categories as $category) {
            $categoryBasePrices    = $basePrices->get($category->id, collect());
            $categoryParentOverrides = $parentOverrides->get($category->id, collect());
            $categoryOwnOverrides  = $ownOverrides->get($category->id, collect());

            $days = [];

            foreach ($dates as $date) {
                $baseRecord     = $categoryBasePrices->get($date);
                $parentOverride = $categoryParentOverrides->get($date);
                $ownOverride    = $categoryOwnOverrides->get($date);

                // Базовая цена с модификатором
                $basePrice = $baseRecord
                    ? (float) round($baseRecord->price * $modifier, 2)
                    : null;

                // Считаем effective_price по приоритетам
                if ($ownOverride && $ownOverride->is_closed) {
                    $effectivePrice = $ownOverride->override_price !== null
                        ? (float) $ownOverride->override_price
                        : ($parentOverride && $parentOverride->override_price !== null
                            ? (float) round($parentOverride->override_price * $modifier, 2)
                            : $basePrice);
                } elseif ($ownOverride && $ownOverride->override_price !== null) {
                    $effectivePrice = (float) $ownOverride->override_price;
                } elseif ($parentOverride && $parentOverride->override_price !== null) {
                    $effectivePrice = (float) round($parentOverride->override_price * $modifier, 2);
                } else {
                    $effectivePrice = $basePrice;
                }

                // Флаги — только свои переопределения
                $isClosed       = $ownOverride ? (bool) $ownOverride->is_closed : false;
                $isPriceOverride = $ownOverride && $ownOverride->override_price !== null;
                $isOverride     = $ownOverride && ($ownOverride->override_price !== null || $ownOverride->is_closed);

                // override_price для отображения — своя цена переопределения
                $overridePrice = $isPriceOverride
                    ? (float) $ownOverride->override_price
                    : null;

                $days[] = [
                    'date'              => $date,
                    'base_price'        => $basePrice,
                    'override_price'    => $overridePrice,
                    'effective_price'   => $effectivePrice,
                    'is_override'       => $isOverride,
                    'is_price_override' => $isPriceOverride,
                    'is_closed'         => $isClosed,
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

        // Базовые цены — только родительских тарифов
        $parentPlanIds = $ratePlans->pluck('parent_id')
            ->filter()
            ->merge($ratePlans->whereNull('parent_id')->pluck('id'))
            ->unique();

        $allPrices = RatePrice::whereIn('rate_plan_id', $parentPlanIds)
            ->whereIn('room_category_id', $categoryIds)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(fn($p) => $p->date->format('Y-m-d'))
            ->map(fn($byDate) => $byDate->groupBy('rate_plan_id')
                ->map(fn($byPlan) => $byPlan->keyBy('room_category_id')));

        // Переопределения всех тарифов
        $allOverrides = RateOverride::whereIn('rate_plan_id', $ratePlanIds)
            ->whereIn('room_category_id', $categoryIds)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(fn($o) => $o->date->format('Y-m-d'))
            ->map(fn($byDate) => $byDate->groupBy('rate_plan_id')
                ->map(fn($byPlan) => $byPlan->keyBy('room_category_id')));

        // Переопределения родителей отдельно для расчёта цены дочерних
        $parentOverrides = RateOverride::whereIn('rate_plan_id', $parentPlanIds)
            ->whereIn('room_category_id', $categoryIds)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(fn($o) => $o->date->format('Y-m-d'))
            ->map(fn($byDate) => $byDate->groupBy('rate_plan_id')
                ->map(fn($byPlan) => $byPlan->keyBy('room_category_id')));

        $result = [];
        $period  = CarbonPeriod::create($dateFrom, $dateTo);

        foreach ($period as $date) {
            $dateStr          = $date->toDateString();
            $minPrice         = null;
            $datePrices       = $allPrices->get($dateStr, collect());
            $dateOverrides    = $allOverrides->get($dateStr, collect());
            $dateParentOverrides = $parentOverrides->get($dateStr, collect());

            foreach ($ratePlans as $plan) {
                $sourcePlanId = $plan->parent_id ?? $plan->id;
                $modifier     = ($plan->parent_id && $plan->modifier_percent !== null)
                    ? (1 + $plan->modifier_percent / 100)
                    : 1;

                foreach ($categories as $category) {
                    // Своё переопределение тарифа
                    $ownOverride = $dateOverrides->get($plan->id)?->get($category->id);

                    // Своё закрытие — пропускаем
                    if ($ownOverride && $ownOverride->is_closed) {
                        continue;
                    }

                    // Своя фиксированная цена — наивысший приоритет
                    if ($ownOverride && $ownOverride->override_price !== null) {
                        $price = (float) $ownOverride->override_price;
                        if ($minPrice === null || $price < $minPrice) {
                            $minPrice = $price;
                        }
                        continue;
                    }

                    // Переопределение родителя — только цена, закрытие игнорируем
                    $parentOverride = $dateParentOverrides->get($sourcePlanId)?->get($category->id);

                    if ($parentOverride && $parentOverride->override_price !== null) {
                        $price = (float) round($parentOverride->override_price * $modifier, 2);
                        if ($minPrice === null || $price < $minPrice) {
                            $minPrice = $price;
                        }
                        continue;
                    }

                    // Базовая цена родителя + модификатор
                    $baseRecord = $datePrices->get($sourcePlanId)?->get($category->id);
                    if (!$baseRecord) {
                        continue;
                    }

                    $price = (float) round($baseRecord->price * $modifier, 2);
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
