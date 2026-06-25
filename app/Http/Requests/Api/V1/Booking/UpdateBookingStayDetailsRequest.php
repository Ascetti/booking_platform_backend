<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStayDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $booking = $this->route('booking');
        $hotelId = $booking->hotel_id;

        return [
            'room_category_id' => [
                'sometimes',
                'integer',
                Rule::exists('room_categories', 'id')
                    ->where('hotel_id', $hotelId),
            ],
            'rate_plan_id' => [
                'sometimes',
                'integer',
                Rule::exists('rate_plans', 'id')
                    ->where('hotel_id', $hotelId)
                    ->where('is_active', true),
            ],
            'check_in_date'  => ['sometimes', 'date', 'after_or_equal:today'],
            'check_out_date' => ['sometimes', 'date', 'after:check_in_date'],
            'adults_count'   => ['sometimes', 'integer', 'min:1', 'max:10'],
            'children_count' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'comment'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
