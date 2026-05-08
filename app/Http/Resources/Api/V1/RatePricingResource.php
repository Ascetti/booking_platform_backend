<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatePricingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date,
            'room_category_id' => $this->room_category_id,
            'price' => (float) $this->final_price,
            'min_stay' => $this->active_min_stay,
            'is_closed' => (bool) $this->active_is_closed,
            'is_overridden' => $this->is_override_active,

            // 'base_info' => [
            //     'price' => (float) $this->base_price,
            //     'min_stay' => $this->base_min_stay,
            // ],

            //'day_of_week' => strtolower(Carbon::parse($this->date)->englishDayOfWeek),
        ];
    }
}
