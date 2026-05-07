<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomCategoryResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'area' => $this->area,
            'base_capacity' => $this->base_capacity,
            'extra_capacity' => $this->extra_capacity,
            'bedding_options' => $this->bedding_options,
            'rooms_count' => $this->whenCounted('rooms'),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
