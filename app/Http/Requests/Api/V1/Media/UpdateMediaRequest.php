<?php

namespace App\Http\Requests\Api\V1\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaRequest extends FormRequest
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
            'room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $this->route('hotel')->id)
            ],
            'file' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ];
    }
}
