<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'status' => new BookingStatusResource($this->whenLoaded('status')),
            'check_in_date' => $this->check_in_date->format('Y-m-d'),
            'check_out_date' => $this->check_out_date->format('Y-m-d'),
            'nights_count' => $this->check_in_date->diffInDays($this->check_out_date),
            'adults_count' => $this->adults_count,
            'children_count' => $this->children_count,
            'room_price_at_booking' => $this->room_price_at_booking,
            'total_price' => $this->total_price,
            'comment' => $this->comment,
            'category' => new RoomCategoryResource($this->whenLoaded('category')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'plan' => new RatePlanResource($this->whenLoaded('plan')),
            'guests' => GuestResource::collection($this->whenLoaded('guests')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
