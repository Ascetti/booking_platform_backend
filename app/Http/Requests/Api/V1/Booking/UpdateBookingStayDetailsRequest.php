<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStayDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $booking = $this->route('booking');
        $hotelId = $booking->hotel_id;

        return [
            'check_in_date' => [
                'required',
                'date',
                $this->check_in_date != $booking->check_in_date->format('Y-m-d')
                    ? 'after_or_equal:today'
                    : ''
            ],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $hotelId)
            ],
            'rate_plan_id' => [
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')->where('hotel_id', $hotelId)
            ],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['required', 'integer', 'min:0'],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)
            ],
        ];
    }
}
