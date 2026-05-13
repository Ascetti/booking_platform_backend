<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Models\Room;
use App\Services\Booking\BookingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
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
        $booking = $this->route('booking');
        $hotelId = $booking->hotel_id;

        return [
            'room_category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $hotelId)
            ],
            'room_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)
            ],
            'rate_plan_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')->where('hotel_id', $hotelId)
            ],
            'status_id' => ['sometimes', 'required', 'integer', 'exists:booking_statuses,id'],
            'check_in_date' => ['sometimes', 'required', 'date'],
            'check_out_date' => ['sometimes', 'required', 'date', 'after:check_in_date'],
            'adults_count' => ['sometimes', 'required', 'integer', 'min:1'],
            'children_count' => ['sometimes', 'required', 'integer', 'min:0'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'guests' => ['sometimes', 'required', 'array', 'min:1'],
            'guests.*.id' => ['sometimes', 'nullable', 'integer', 'exists:guests,id'],
            'guests.*.first_name' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.last_name' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'guests.*.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guests.*.is_primary' => ['required_with:guests', 'boolean'],
            'services' => ['sometimes', 'nullable', 'array'],
            'services.*.id' => [
                'required_with:services',
                'integer',
                Rule::exists('services', 'id')->where('hotel_id', $hotelId)
            ],
            'services.*.quantity' => ['required_with:services', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any()) 
                    return;
                $booking = $this->route('booking');
                $data = $this->validated();
                $service = app(BookingService::class);

                // 1. Собираем полный контекст (новое из запроса ИЛИ старое из базы)
                $checkIn = $data['check_in_date'] ?? $booking->getRawOriginal('check_in_date');
                $checkOut = $data['check_out_date'] ?? $booking->getRawOriginal('check_out_date');
                $categoryId = $data['room_category_id'] ?? $booking->room_category_id;
                $roomId = array_key_exists('room_id', $data) ? $data['room_id'] : $booking->room_id;
                $ratePlanId = $data['rate_plan_id'] ?? $booking->rate_plan_id;

                // 2. Проверка соответствия номера категории (если что-то из этого изменилось)
                if ($roomId) {
                    $room = Room::find($roomId);
                    if ($room->room_category_id !== (int)$categoryId) {
                        $validator->errors()->add('room_id', 'Выбранный номер не входит в указанную категорию.');
                    }
                }

                // 3. Проверка цен и ограничений (только если затронуты даты, тариф или категория)
                $logicFields = ['check_in_date', 'check_out_date', 'rate_plan_id', 'room_category_id'];
                if (array_intersect_key($data, array_flip($logicFields))) {

                    if (!$service->hasPricesForPeriod($categoryId, $ratePlanId, $checkIn, $checkOut)) {
                        $validator->errors()->add('rate_plan_id', 'На выбранный период нет цен.');
                    }

                    $restrictionError = $service->checkRatePlanRestrictions($ratePlanId, $categoryId, $checkIn, $checkOut);
                    if ($restrictionError) {
                        $validator->errors()->add('rate_plan_id', $restrictionError);
                    }
                }

                // 4. Проверка доступности (самое важное!)
                // Передаем ID текущей брони, чтобы сервис "игнорировал" её при проверке занятости
                if (!$service->isRoomAvailable($categoryId, $roomId, $checkIn, $checkOut, $booking->id)) {
                    $validator->errors()->add('room_category_id', 'На эти даты мест больше нет (с учетом изменений).');
                }
            }
        ];
    }
}
