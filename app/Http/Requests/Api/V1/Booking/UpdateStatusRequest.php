<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\BookingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(BookingStatusEnum::class),
            ],
            // // При заселении номер обязателен
            // 'room_id' => [
            //     Rule::requiredIf(fn() => $this->input('status') === BookingStatusEnum::CHECKED_IN->value),
            //     'nullable',
            //     'integer',
            //     Rule::exists('rooms', 'id'),
            // ],
        ];
    }
}