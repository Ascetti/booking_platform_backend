<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\DocumentTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hotelId = $this->route('booking')->hotel_id;

        return [
            'guests'               => ['required', 'array', 'min:1'],
            'guests.*.id'          => ['nullable', 'integer', Rule::exists('guests', 'id')
                ->where('hotel_id', $hotelId)],
            'guests.*.first_name'  => ['required', 'string', 'max:255'],
            'guests.*.last_name'   => ['required', 'string', 'max:255'],
            'guests.*.middle_name' => ['nullable', 'string', 'max:255'],
            'guests.*.birth_date'  => ['nullable', 'date', 'before:today'],
            'guests.*.document_type'   => ['nullable', Rule::enum(DocumentTypeEnum::class)],
            'guests.*.document_number' => ['nullable', 'string', 'max:50'],
            'guests.*.email'       => ['nullable', 'email'],
            'guests.*.phone'       => ['nullable', 'string'],
            'guests.*.is_primary'  => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $guests  = $this->input('guests', []);
                $booking = $this->route('booking');

                // Ровно один заказчик
                $primaryGuests = collect($guests)->filter(fn($g) => !empty($g['is_primary']));
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

                // Количество гостей не превышает состав бронирования
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
