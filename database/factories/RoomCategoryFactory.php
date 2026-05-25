<?php

namespace Database\Factories;

use App\Models\RoomCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Standard',
                'Econom',
                'Family',
                'Deluxe',
                'Suite',
                'President Suite',
            ]),
            'description' => fake()->sentence(10),
            'area' => fake()->numberBetween(10, 100),
            'base_capacity' => fake()->numberBetween(1, 4),
            'extra_capacity' => fake()->numberBetween(0, 2),
            'bedding_options' => fake()->randomElement([
                '1 double bed',
                '2 single beds',
                '1 king-size bed',
                '1 king-size bed + 1 single bed',
                '1 double bed + 2 single bed',
            ]),
        ];
    }
}
