<?php

namespace App\Http\Requests\Api\V1\RatePlan;

use App\Enums\MealPlanEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

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
        $ratePlan = $this->route('rate_plan');
        $hotelId = $this->route('rate_plan')->hotel_id;
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('rate_plans', 'name')
                    ->where(fn($query) => $query->where('hotel_id', $hotelId))
                    ->ignore($ratePlan->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('rate_plans', 'id')->where(function ($query) use ($ratePlan, $hotelId) {
                    $query->where('hotel_id', $hotelId)
                        ->whereNull('parent_id')
                        ->where('id', '<>', $ratePlan->id);
                }),
            ],
            'modifier_percent' => ['sometimes', 'nullable', 'integer', 'min:-1000', 'max:1000'],
            'meal_plan' => ['sometimes', 'required', Rule::enum(MealPlanEnum::class)],
            'cancellation_free_days' => ['sometimes', 'required', 'integer', 'min:0'],
            'cancellation_penalty_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'prepayment_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'min_stay_days' => ['sometimes', 'required', 'integer', 'min:1'],
            'min_days_before_arrival' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_days_before_arrival' => ['sometimes', 'nullable', 'integer', 'min:0', 'gte:min_days_before_arrival'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            // 'pricing' => ['sometimes', 'required_without:parent_id', 'array'],
            // 'pricing.categories' => ['required_with:pricing', 'array','min:1'],
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

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ratePlan = $this->route('rate_plan');
                $parentId = $this->has('parent_id') ? $this->input('parent_id') : $ratePlan->parent_id;
                $modifierPercent = $this->has('modifier_percent') ? $this->input('modifier_percent') : $ratePlan->modifier_percent;
                if ($parentId !== null && $modifierPercent === null) {
                    $validator->errors()->add('modifier_percent', 'The modifier percent field is required for dependent rate plans.');
                }
                if ($parentId === null && $this->filled('modifier_percent')) {
                    $validator->errors()->add('modifier_percent', 'The modifier percent can be used only for dependent rate plans.');
                }
            }
        ];
    }
}
