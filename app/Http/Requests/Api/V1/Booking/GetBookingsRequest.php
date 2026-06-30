<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\BookingStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetBookingsRequest extends FormRequest
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
        return [
            'search'     => ['sometimes', 'string', 'max:255'],
            'statuses'   => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::enum(BookingStatusEnum::class)],
            'date_from'  => ['sometimes', 'date'],
            'date_to'    => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
