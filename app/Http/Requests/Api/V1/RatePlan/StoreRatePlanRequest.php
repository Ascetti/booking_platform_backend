<?php

namespace App\Http\Requests\Api\V1\RatePlan;

use App\Enums\MealPlanEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRatePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('rate_plans', 'id')->where(function ($query) {
                    $query->where('hotel_id', $this->route('hotel')->id);
                }),
            ],
            'meal_plan' => ['required', new Enum(MealPlanEnum::class)],
            'modifier_percent' => ['nullable', 'integer', 'min:-100', 'max:1000'],
            'min_stay_days' => ['sometimes', 'integer', 'min:1'],
            'min_days_before_arrival' => ['nullable', 'integer', 'min:0'],
            'max_days_before_arrival' => ['nullable', 'integer', 'min:0', 'gte:min_days_before_arrival'],
            'cancellation_free_days' => ['sometimes', 'integer', 'min:0'],
            'cancellation_penalty_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'prepayment_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'base_prices' => ['required', 'array', 'min:1'],
            'base_prices.*.room_category_id' => [
                'required', 
                'integer', 
                Rule::exists('room_categories', 'id')->where('hotel_id', $this->route('hotel')->id)
            ],
            'base_prices.*.monday'    => ['required', 'numeric', 'min:0'],
            'base_prices.*.tuesday'   => ['required', 'numeric', 'min:0'],
            'base_prices.*.wednesday' => ['required', 'numeric', 'min:0'],
            'base_prices.*.thursday'  => ['required', 'numeric', 'min:0'],
            'base_prices.*.friday'    => ['required', 'numeric', 'min:0'],
            'base_prices.*.saturday'  => ['required', 'numeric', 'min:0'],
            'base_prices.*.sunday'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
