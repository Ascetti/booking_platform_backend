<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
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
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'child_age_threshold' => $this->child_age_threshold,
            'check_in_time' => $this->check_in_time?->format('H:i'),
            'check_out_time' => $this->check_out_time?->format('H:i'),
        ];
    }
}
