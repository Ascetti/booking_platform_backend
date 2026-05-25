<?php

namespace App\Http\Requests\Api\V1\Room;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
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
        $categoryId = $this->route('room_category')->id;
        return [
            'name' => [
                'bail',
                'required_without:names',
                'string',
                'max:255',
                Rule::unique('rooms', 'name')->where(
                    fn($query) => $query->where('room_category_id', $categoryId)
                ),
            ],
            'names' => ['required_without:name', 'array', 'min:1'],
            'names.*' => [
                'distinct',
                'string',
                'max:255',
                Rule::unique('rooms', 'name')->where(
                    fn($query) => $query->where('room_category_id', $categoryId)
                ),
            ],
        ];
    }
}
