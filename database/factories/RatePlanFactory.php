<?php

namespace Database\Factories;

use App\Enums\MealPlanEnum;
use App\Models\Hotel;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id'                     => Hotel::factory(),
            'parent_id'                    => null,
            'name'                         => $this->faker->words(2, true),
            'description'                  => $this->faker->sentence(),
            'meal_plan'                    => $this->faker->randomElement(MealPlanEnum::cases())->value,
            'modifier_percent'             => null,
            'cancellation_free_days'       => $this->faker->numberBetween(1, 5),
            'cancellation_penalty_percent' => $this->faker->numberBetween(10, 50),
            'prepayment_percent'           => $this->faker->randomElement([0, 20, 30, 50]),
            'min_stay_days'                => 1,
            'min_days_before_arrival'      => null,
            'max_days_before_arrival'      => null,
            'is_active'                    => true,
        ];
    }

    public function child(RatePlan $parent): static
    {
        return $this->state(fn() => [
            'hotel_id'         => $parent->hotel_id,
            'parent_id'        => $parent->id,
            'modifier_percent' => $this->faker->randomElement([10, 15, 20, 25]),
        ]);
    }
}
