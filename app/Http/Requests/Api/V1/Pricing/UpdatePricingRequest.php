<?php

namespace App\Http\Requests\Api\V1\Pricing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            
            'days_of_week' => ['sometimes', 'array'],
            'days_of_week.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],

            'room_categories' => ['required', 'array', 'min:1'],
            'room_categories.*' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $this->route('hotel')->id)
            ],

            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_stay' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_closed' => ['sometimes', 'boolean'],

            'any_change' => [
                Rule::requiredIf(function () {
                    return !($this->has('price') || $this->has('min_stay') || $this->has('is_closed'));
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'any_change' => 'You need to make any change',
            'room_category_ids.*.exists' => 'There is no such category for your hotel',
        ];
    }
}
