<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Hotel',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'description' => fake()->paragraph(3),
            'timezone' => fake()->timezone(),
            'child_age_threshold' => fake()->numberBetween(2, 12),
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ];
    }
}
