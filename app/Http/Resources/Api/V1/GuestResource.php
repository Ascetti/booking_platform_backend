<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isFromBooking = $this->pivot !== null;

        return [
            'id' => $this->id,
            'first_name' => $isFromBooking ? $this->pivot->first_name : $this->first_name,
            'last_name' => $isFromBooking ? $this->pivot->last_name : $this->last_name,
            'middle_name' => $this->middle_name,
            'full_name' => trim(sprintf(
                '%s %s %s',
                $isFromBooking ? $this->pivot->last_name : $this->last_name,
                $isFromBooking ? $this->pivot->first_name : $this->first_name,
                $this->middle_name
            )),
            'email' => $isFromBooking ? $this->pivot->email : $this->email,
            'phone' => $isFromBooking ? $this->pivot->phone : $this->phone,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'document_type' => $this->document_type?->value,
            'document_number' => $this->document_number,
            'is_primary' => $this->whenPivotLoaded('booking_guest', function () {
                return (bool)$this->pivot->is_primary;
            }),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'bookings_count' => $this->whenCounted('bookings'),
        ];
    }
}
