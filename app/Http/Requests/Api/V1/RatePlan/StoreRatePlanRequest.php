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
        $hotelId = $this->route('hotel')->id;
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rate_plans', 'name')->where(
                    fn($query) => $query->where('hotel_id', $hotelId)
                ),
            ],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('rate_plans', 'id')->where(function ($query) use ($hotelId) {
                    $query->where('hotel_id', $hotelId)
                        ->whereNull('parent_id');
                }),
            ],
            'modifier_percent' => ['required_with:parent_id', 'exclude_if:parent_id,null', 'integer', 'min:-1000', 'max:1000'],
            'meal_plan' => ['required', Rule::enum(MealPlanEnum::class)],
            'cancellation_free_days' => ['sometimes', 'required', 'integer', 'min:0'],
            'cancellation_penalty_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'prepayment_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'min_stay_days' => ['sometimes', 'required', 'integer', 'min:1'],
            'min_days_before_arrival' => ['nullable', 'integer', 'min:0'],
            'max_days_before_arrival' => ['nullable', 'integer', 'min:0', 'gte:min_days_before_arrival'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            // 'pricing' => ['required_without:parent_id', 'array'],
            // 'pricing.categories' => ['required', 'array','min:1'],
            // 'pricing.categories.*.room_category_id' => [
            //     'required',
            //     'integer',
            //     Rule::exists('room_categories', 'id')
            //         ->where(function ($query) use ($hotelId) {
            //             $query->where('hotel_id', $hotelId);
            //         }),
            // ],
            // 'pricing.categories.*.weekdays' => ['required', 'array'],
            // 'pricing.categories.*.weekdays.monday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.tuesday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.wednesday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.thursday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.friday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.saturday' => ['required', 'numeric', 'min:0'],
            // 'pricing.categories.*.weekdays.sunday' => ['required', 'numeric', 'min:0'],
        ];
    }
}
