<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatePlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'parent_id' => $this->parent_id,
            'modifier_percent' => $this->modifier_percent,
            'name' => $this->name,
            'description' => $this->description,
            'meal_plan' => $this->meal_plan->value,
            'meal_plan_label' => $this->meal_plan->label(),
            'constraints' => [
                'min_stay_days' => $this->min_stay_days,
                'min_days_before_arrival' => $this->min_days_before_arrival,
                'max_days_before_arrival' => $this->max_days_before_arrival,
            ],
            'cancellation' => [
                'free_days' => $this->cancellation_free_days,
                'penalty_percent' => $this->cancellation_penalty_percent,
            ],
            'prepayment_percent' => $this->prepayment_percent,
            'is_active' => $this->is_active,
            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            // 'created_at' => $this->created_at?->format('Y-m-d H:i:s')
        ];
    }

    // protected function prepareBasePrices(): array
    // {
    //     if (!$this->relationLoaded('prices')) {
    //         return [];
    //     }

    //     $startOfWeek = Carbon::now()->addWeek()->startOfWeek();
    //     $endOfWeek = (clone $startOfWeek)->endOfWeek();

    //     return $this->prices
    //         ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
    //         ->groupBy('room_category_id')
    //         ->map(function ($prices, $categoryId) {
    //             $categoryData = ['room_category_id' => $categoryId];
    //             foreach ($prices as $price) {
    //                 $dayKey = strtolower(Carbon::parse($price->date)->englishDayOfWeek);
    //                 $categoryData[$dayKey] = $price->price;
    //             }

    //             return $categoryData;
    //         })
    //         ->values()
    //         ->all();
    // }
}
