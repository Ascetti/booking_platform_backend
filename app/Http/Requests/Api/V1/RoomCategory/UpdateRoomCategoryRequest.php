<?php

namespace App\Http\Requests\Api\V1\RoomCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'area' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'extra_capacity' => ['sometimes', 'required', 'integer', 'min:0'],
            'bedding_options' => ['sometimes', 'required', 'string', 'max:255'],
            // 'amenities' => ['sometimes', 'array'],
            // 'amenities.*' => ['integer', 'exists:amenities,id'],
        ];
    }
}
