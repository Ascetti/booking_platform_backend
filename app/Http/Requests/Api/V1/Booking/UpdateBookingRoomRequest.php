<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRoomRequest extends FormRequest
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
        $hotelId = $this->route('booking')->hotel_id;

        return [
            'room_id' => [
                'required',
                'integer',
                Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)
            ],
        ];
    }
}
