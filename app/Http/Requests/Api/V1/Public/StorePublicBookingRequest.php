<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Enums\ServiceTypeEnum;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hotelId = $this->route('hotel')->id;

        return [
            'room_category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')
                    ->where('hotel_id', $hotelId),
            ],
            'rate_plan_id' => [
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')
                    ->where('hotel_id', $hotelId)
                    ->where('is_active', true),
            ],
            'check_in_date'  => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults_count'   => ['required', 'integer', 'min:1', 'max:10'],
            'children_count' => ['required', 'integer', 'min:0', 'max:10'],
            'comment'        => ['nullable', 'string', 'max:1000'],

            // Гости — минимум один заказчик
            'guests'               => ['required', 'array', 'min:1'],
            'guests.*.first_name'  => ['required', 'string', 'max:255'],
            'guests.*.last_name'   => ['required', 'string', 'max:255'],
            'guests.*.is_primary'  => ['required', 'boolean'],
            'guests.*.email'       => ['nullable', 'email'],
            'guests.*.phone'       => ['nullable', 'string'],

            // Услуги — необязательны
            'services'            => ['nullable', 'array'],
            'services.*.id'       => [
                'required',
                'integer',
                Rule::exists('services', 'id')
                    ->where('hotel_id', $hotelId),
            ],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $guests = $this->input('guests', []);

                // Ровно один заказчик
                $primaryCount = collect($guests)
                    ->filter(fn($g) => !empty($g['is_primary']))
                    ->count();

                if ($primaryCount !== 1) {
                    $validator->errors()->add(
                        'guests',
                        'Exactly one guest must be marked as primary.'
                    );
                    return;
                }

                // У заказчика должен быть контакт
                $primary = collect($guests)->firstWhere('is_primary', true);
                if (empty($primary['email']) || empty($primary['phone'])) {
                    $validator->errors()->add(
                        'guests',
                        'Primary guest must have both email and phone.'
                    );
                }

                $services = $this->input('services', []);

                foreach ($services as $index => $serviceItem) {
                    $service = Service::find($serviceItem['id'] ?? null);

                    if (!$service) continue;

                    // PER_STAY — единоразовая услуга, quantity всегда 1
                    if (
                        $service->price_type === ServiceTypeEnum::PER_STAY
                        && $serviceItem['quantity'] !== 1
                    ) {
                        $validator->errors()->add(
                            "services.{$index}.quantity",
                            'Quantity must be 1 for one-time services.'
                        );
                    }
                }
            }
        ];
    }
}
