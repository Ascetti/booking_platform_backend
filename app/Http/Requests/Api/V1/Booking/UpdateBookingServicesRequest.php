<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\ServiceTypeEnum;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hotelId = $this->route('booking')->hotel_id;

        return [
            'services'              => ['nullable', 'array'],
            'services.*.id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')
                    ->where('hotel_id', $hotelId),
            ],
            'services.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
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
