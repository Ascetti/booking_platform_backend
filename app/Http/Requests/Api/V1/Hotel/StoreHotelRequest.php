<?php

namespace App\Http\Requests\Api\V1\Hotel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHotelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'timezone' => ['sometimes', 'required', 'string', 'max:50'],
            'child_age_threshold' => ['sometimes', 'required', 'integer', 'min:0', 'max:18'],
            'check_in_time' => ['sometimes', 'required', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'required', 'date_format:H:i'],
        ];
    }
}
