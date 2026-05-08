<?php

namespace App\Http\Requests\Api\V1\RatePlan;

use App\Enums\MealPlanEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRatePlanRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('rate_plans', 'id')
                    ->where('hotel_id', $this->rate_plan->hotel_id)
                    ->whereNot('id', $this->rate_plan->id),
            ],
            'meal_plan' => ['sometimes', new Enum(MealPlanEnum::class)],
            'modifier_percent' => ['sometimes', 'nullable', 'integer'],
            'min_stay_days' => ['sometimes', 'integer', 'min:1'],
            'cancellation_free_days' => ['sometimes', 'integer', 'min:0'],
            'cancellation_penalty_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'prepayment_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'base_prices' => ['sometimes', 'array', 'min:1'],
            'base_prices.*.room_category_id' => [
                'sometimes', 
                'integer', 
                Rule::exists('room_categories', 'id')->where('hotel_id', $this->route('hotel')->id)
            ],
            'base_prices.*.monday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.tuesday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.wednesday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.thursday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.friday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.saturday' => ['sometimes', 'numeric', 'min:0'],
            'base_prices.*.sunday' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
