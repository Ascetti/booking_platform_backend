<?php

namespace App\Http\Requests\Api\V1\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBookingGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests'               => ['required', 'array', 'min:1'],
            'guests.*.first_name'  => ['required', 'string', 'max:255'],
            'guests.*.last_name'   => ['required', 'string', 'max:255'],
            'guests.*.email'       => ['nullable', 'email'],
            'guests.*.phone'       => ['nullable', 'string'],
            'guests.*.is_primary'  => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $guests = $this->input('guests', []);
                $booking = $this->route('booking');

                $primaryGuests = collect($guests)
                    ->filter(fn($g) => !empty($g['is_primary']));

                if ($primaryGuests->count() !== 1) {
                    $validator->errors()->add(
                        'guests',
                        'Exactly one guest must be marked as primary.'
                    );
                    return;
                }

                $primary = $primaryGuests->first();
                if (empty($primary['email']) || empty($primary['phone'])) {
                    $validator->errors()->add(
                        'guests',
                        'Primary guest must have both email and phone.'
                    );
                }

                // Проверяем что количество гостей не превышает состав бронирования
                $maxGuests = $booking->adults_count + $booking->children_count;
                if (count($guests) > $maxGuests) {
                    $validator->errors()->add(
                        'guests',
                        "Cannot add more guests than booking capacity ({$maxGuests})."
                    );
                }
            }
        ];
    }
}
