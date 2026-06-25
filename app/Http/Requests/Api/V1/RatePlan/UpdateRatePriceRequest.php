<?php

namespace App\Http\Requests\Api\V1\RatePlan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRatePriceRequest extends FormRequest
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
        $hotelId = $this->route('rate_plan')->hotel_id;
        return [
            'base_prices' => ['required', 'array', 'min:1'],
            'base_prices.*.room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')
                    ->where('hotel_id', $hotelId),
            ],
            'base_prices.*.day_prices' => ['required', 'array'],
            'base_prices.*.day_prices.mon' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.tue' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.wed' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.thu' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.fri' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.sat' => ['nullable', 'numeric', 'min:0'],
            'base_prices.*.day_prices.sun' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
