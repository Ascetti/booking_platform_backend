<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Booking\BookingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
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
        $hotelId = $this->route('hotel');

        return [
            'room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where('hotel_id', $hotelId)
            ],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)
            ],
            'rate_plan_id' => [
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')->where('hotel_id', $hotelId)
            ],
            'status_id' => ['required', 'integer', 'exists:booking_statuses,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['required', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.id' => ['sometimes', 'nullable', 'integer', 'exists:guests,id'],
            'guests.*.first_name' => ['required', 'string', 'max:255'],
            'guests.*.last_name' => ['required', 'string', 'max:255'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:50'],
            'guests.*.is_primary' => ['required', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*.id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('hotel_id', $hotelId)
            ],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any()) {
                    return;
                }
                $data = $this->validated();
                $service = app(BookingService::class);
                if (!empty($data['room_id'])) {
                    $room = Room::find($data['room_id']);
                    if ($room->room_category_id !== (int)$data['room_category_id']) {
                        $validator->errors()->add('room_id', 'Выбранный номер не входит в указанную категорию.');
                    }
                }
                $category = RoomCategory::find($data['room_category_id']);
                $totalCapacity = $category->base_capacity + $category->base_capacity;
                if (($data['adults_count'] + intdiv($data['children_count'], 2)) > $totalCapacity) {
                    return "Общее количество гостей превышает вместимость номера ({$totalCapacity} чел.).";
                }
                if (!$service->hasPricesForPeriod($data['room_category_id'], $data['rate_plan_id'], $data['check_in_date'], $data['check_out_date'])) {
                    $validator->errors()->add('rate_plan_id', 'На выбранный период или категорию не установлены цены в данном тарифе.');
                }
                $restrictionError = $service->checkRatePlanRestrictions($data['rate_plan_id'], $data['room_category_id'], $data['check_in_date'], $data['check_out_date']);
                if ($restrictionError) {
                    $validator->errors()->add('rate_plan_id', $restrictionError);
                }
                if (!$service->isRoomAvailable($data['room_category_id'], $data['room_id'] ?? null, $data['check_in_date'], $data['check_out_date'])) {
                    $field = $data['room_id'] ? 'room_id' : 'room_category_id';
                    $validator->errors()->add($field, 'На выбранные даты нет свободных мест.');
                }
            }
        ];
    }
}
