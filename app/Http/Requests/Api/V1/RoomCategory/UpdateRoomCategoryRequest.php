<?php

namespace App\Http\Requests\Api\V1\RoomCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomCategoryRequest extends FormRequest
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
        $roomCategory = $this->route('room_category');
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('room_categories', 'name')
                    ->where(fn($query) => $query->where('hotel_id', $roomCategory->hotel_id))
                    ->ignore($roomCategory->id),
            ],
            'area' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string'],
            'base_capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'extra_capacity' => ['sometimes', 'required', 'integer', 'min:0'],
            'bedding_options' => ['sometimes', 'required', 'string', 'max:255'],
            // 'amenities' => ['sometimes', 'array'],
            // 'amenities.*' => ['integer', 'exists:amenities,id'],
        ];
    }
}
