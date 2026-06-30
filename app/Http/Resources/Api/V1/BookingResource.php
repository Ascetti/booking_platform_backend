<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'hotel_id' => $this->hotel_id,

            // Вложенные ресурсы — переиспользуем существующие
            'status'    => new BookingStatusResource($this->whenLoaded('status')),
            'category'  => new RoomCategoryResource($this->whenLoaded('category')),
            'room'      => new RoomResource($this->whenLoaded('room')),
            'rate_plan' => new RatePlanResource($this->whenLoaded('plan')),

            'check_in_date'  => $this->check_in_date->format('Y-m-d'),
            'check_out_date' => $this->check_out_date->format('Y-m-d'),
            'nights'         => $this->calculateNights(),

            'adults_count'   => $this->adults_count,
            'children_count' => $this->children_count,

            'room_price_at_booking' => $this->room_price_at_booking,
            'total_price'           => $this->total_price,

            'comment' => $this->comment,

            'guests' => $this->whenLoaded('guests', function () {
                return $this->guests->map(fn($guest) => [
                    'id'              => $guest->id,
                    'is_primary'      => (bool) $guest->pivot->is_primary,
                    'first_name'      => $guest->first_name,
                    'last_name'       => $guest->last_name,
                    'middle_name'     => $guest->middle_name,
                    'birth_date'      => $guest->birth_date?->format('Y-m-d'),
                    'document_type'   => $guest->document_type,
                    'document_number' => $guest->document_number,
                    'email'           => $guest->email,
                    'phone'           => $guest->phone,
                ]);
            }),

            // Услуги — цена из снимка (pivot)
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(fn($service) => [
                    'id'               => $service->id,
                    'name'             => $service->name,
                    'price_type'       => $service->price_type,
                    'quantity'         => $service->pivot->quantity,
                    'price_at_booking' => $service->pivot->price_at_booking,
                    'total'            => $service->pivot->quantity * $service->pivot->price_at_booking,
                ]);
            }),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
