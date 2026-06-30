<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $booking = $this->route('booking');

        return [
            'room_id' => [
                'nullable',
                'integer',
                // Номер должен принадлежать категории бронирования
                Rule::exists('rooms', 'id')
                    ->where('room_category_id', $booking->room_category_id)
                    ->where('is_active', true),
            ],
        ];
    }
}