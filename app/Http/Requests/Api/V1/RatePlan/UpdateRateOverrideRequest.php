<?php

namespace App\Http\Requests\Api\V1\RatePlan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRateOverrideRequest extends FormRequest
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
        $sourcePlanId = $ratePlan->parent_id ?? $ratePlan->id;

        $validCategoryIds = \App\Models\RatePrice::where('rate_plan_id', $sourcePlanId)
            ->distinct()
            ->pluck('room_category_id')
            ->toArray();

        return [
            'date_from'           => ['required', 'date'],
            'date_to'             => ['required', 'date', 'after_or_equal:date_from'],
            'room_categories'   => ['required', 'array', 'min:1'],
            'room_categories.*' => ['integer', Rule::in($validCategoryIds)],
            'override_price'      => ['nullable', 'numeric', 'min:0'],
            'is_closed'           => ['required', 'boolean'],
        ];
    }
}
