<?php

namespace Database\Factories;

use App\Enums\ServiceTypeEnum;
use App\Models\Hotel;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Трансфер из аэропорта',
            'Парковка',
            'Завтрак',
            'Экскурсия по городу',
            'Спа-процедура',
            'Прокат велосипеда',
            'Трансфер на вокзал',
        ];

        return [
            'hotel_id'    => Hotel::factory(),
            'name'        => $this->faker->randomElement($services),
            'description' => $this->faker->sentence(),
            'price'       => $this->faker->randomElement([500, 800, 1000, 1200, 1500, 2000]),
            'price_type'  => $this->faker->randomElement(ServiceTypeEnum::cases())->value,
        ];
    }
}
