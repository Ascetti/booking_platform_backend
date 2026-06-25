<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'hotel_id'        => $this->hotel_id,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'middle_name'     => $this->middle_name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'birth_date'      => $this->birth_date?->format('Y-m-d'),
            'document_type'   => $this->document_type,
            'document_number' => $this->document_number,

            // История бронирований — только если загружена
            'bookings' => $this->whenLoaded('bookings', function () {
                return $this->bookings->map(fn($booking) => [
                    'id'             => $booking->id,
                    'check_in_date'  => $booking->check_in_date->format('Y-m-d'),
                    'check_out_date' => $booking->check_out_date->format('Y-m-d'),
                    'status'         => $booking->status->slug,
                    'total_price'    => $booking->total_price,
                ]);
            }),

            // 'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            // 'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}