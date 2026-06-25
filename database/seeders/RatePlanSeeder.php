<?php

namespace Database\Seeders;

use App\Enums\MealPlanEnum;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RatePlanSeeder extends Seeder
{
    const DAY_MAP = [
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
        0 => 'sun',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotels = Hotel::with('categories')->get();

        foreach ($hotels as $hotel) {
            // Создаём базовый тариф через фабрику
            $basePlan = RatePlan::factory()->create([
                'hotel_id'    => $hotel->id,
                'name'        => 'Стандартный',
                'parent_id'   => null,
                'modifier_percent' => null,
            ]);

            // Создаём дочерний тариф через состояние фабрики
            RatePlan::factory()->child($basePlan)->create([
                'name' => 'Завтрак включён',
            ]);

            // Базовые цены — здесь фабрика не подходит
            // нужна конкретная логика по датам и категориям
            $categoryPrices = [
                'Standard' => [
                    'mon' => 3000,
                    'tue' => 3000,
                    'wed' => 3000,
                    'thu' => 3000,
                    'fri' => 4500,
                    'sat' => 4500,
                    'sun' => 3500,
                ],
                'Deluxe' => [
                    'mon' => 5000,
                    'tue' => 5000,
                    'wed' => 5000,
                    'thu' => 5000,
                    'fri' => 7000,
                    'sat' => 7000,
                    'sun' => 6000,
                ],
                'Suite' => [
                    'mon' => 9000,
                    'tue' => 9000,
                    'wed' => 9000,
                    'thu' => 9000,
                    'fri' => 12000,
                    'sat' => 12000,
                    'sun' => 10000,
                ],
            ];

            $period = CarbonPeriod::create(Carbon::today(), Carbon::today()->addYear());

            foreach ($hotel->categories as $category) {
                $prices = $categoryPrices[$category->name] ?? $categoryPrices['Standard'];
                $rows   = [];

                foreach ($period as $date) {
                    $dayKey = self::DAY_MAP[$date->dayOfWeek];
                    $rows[] = [
                        'rate_plan_id'     => $basePlan->id,
                        'room_category_id' => $category->id,
                        'date'             => $date->toDateString(),
                        'price'            => $prices[$dayKey],
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    RatePrice::insert($chunk);
                }
            }
        }
    }
}
