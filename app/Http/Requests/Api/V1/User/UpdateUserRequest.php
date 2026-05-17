<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends FormRequest
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
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user') ?? $this->user()?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'min:10', 'max:20', 'regex:/^(\+7|8|7)[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}$/'],
            'password' => ['sometimes', 'nullable', 'string', Rules\Password::defaults()],
            // 'role_id' => ['required', 'integer', 'exists:roles,id'],
            // 'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ];
    }
}
