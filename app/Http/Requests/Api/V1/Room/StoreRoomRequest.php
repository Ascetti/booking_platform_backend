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
        return [
            'name' => ['required_without:names','string','max:255'],
            'names' => ['required_without:name','array','min:1'],
            'names.*' => ['string', 'max:255'],
            'room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $this->route('hotel')->id)
            ],
        ];
    }
}
