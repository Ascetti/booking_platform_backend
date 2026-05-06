<?php

namespace App\Http\Requests\Api\V1\Hotel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelRequest extends FormRequest
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
            'address' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'child_age_threshold' => ['sometimes', 'integer', 'min:0', 'max:18'],
            'check_in_time' => ['sometimes', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'date_format:H:i'],
        ];
    }
}
