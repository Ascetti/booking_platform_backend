<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncBookingGuestsRequest extends FormRequest
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
            'guests' => ['present', 'array', 'min:1'],
            'guests.*.id' => ['nullable', 'integer', 'exists:guests,id'],
            'guests.*.first_name' => ['required_without:guests.*.id', 'string', 'max:255'],
            'guests.*.last_name' => ['required_without:guests.*.id', 'string', 'max:255'],
            'guests.*.is_primary' => ['required', 'boolean'],
        ];
    }
}
