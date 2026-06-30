<?php

namespace App\Http\Requests\Api\V1\RoomCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomCategoryRequest extends FormRequest
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
                Rule::unique('room_categories', 'name')->where(
                    fn($query) => $query->where('hotel_id', $hotelId)
                ),
            ],
            'area' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string',],
            'base_capacity' => ['required', 'integer', 'min:1'],
            'extra_capacity' => ['required', 'integer', 'min:0'],
            'bedding_options' => ['required', 'string', 'max:255'],
            // 'amenities' => ['nullable', 'array'],
            // 'amenities.*' => ['integer', 'exists:amenities,id'],
        ];
    }
}
