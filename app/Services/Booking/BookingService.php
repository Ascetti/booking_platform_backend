<?php

namespace App\Services\Booking;

use App\Enums\BookingStatusEnum;
use App\Enums\ServiceTypeEnum;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function createBooking(Hotel $hotel, array $data): Booking
    {
        return DB::transaction(function () use ($hotel, $data) {
            RoomCategory::where('id', $data['room_category_id'])->lockForUpdate()->first();

            $checkIn = Carbon::parse($data['check_in_date']);
            $checkOut = Carbon::parse($data['check_out_date']);
            $nights = $checkIn->diffInDays($checkOut);
            $persons = $data['adults_count'] + $data['children_count'];

            $roomPriceAtBooking = $this->calculateRoomPrice(
                $data['room_category_id'],
                $data['rate_plan_id'],
                $data['check_in_date'],
                $data['check_out_date']
            );
            if ($roomPriceAtBooking === null) {
                throw new Exception('throw new \Exception("Не удалось рассчитать стоимость проживания: отсутствуют цены на выбранные даты.');
            }

            $guestsData = $this->processGuests($data['guests']);
            $servicesData = $this->processServices($data['services'] ?? [], $nights, $persons);

            $booking = $hotel->bookings()->create([
                'room_category_id' => $data['room_category_id'],
                'room_id' => $data['room_id'] ?? null,
                'rate_plan_id' => $data['rate_plan_id'],
                'status_id' => $data['status_id'],
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'adults_count' => $data['adults_count'],
                'children_count' => $data['children_count'],
                'room_price_at_booking' => $roomPriceAtBooking,
                'total_price' => $roomPriceAtBooking + $servicesData['total_price'],
                'comment' => $data['comment'] ?? null,
            ]);

            $booking->guests()->attach($guestsData);
            $booking->services()->attach($servicesData['pivot_data']);

            return $booking->load(['guests', 'services', 'room', 'room_category', 'plan']);
        });
    }

    private function processGuests(array $data): array
    {
        $guestsData = [];

        foreach ($data as $item) {
            $profile = Guest::query()->where('last_name', $item['last_name'])
                ->where(function ($query) use ($item) {
                    if (!empty($item['email']))
                        $query->where('email', $item['email']);
                    if (!empty($item['phone']))
                        $query->orWhere('phone', $item['phone']);
                })->first();
            if (!$profile) {
                $profile = Guest::create([
                    'first_name' => $item['first_name'],
                    'last_name' => $item['last_name'],
                    'email' => $item['email'] ?? null,
                    'phone' => $item['phone'] ?? null,
                ]);
            }
            $guestsData[$profile->id] = [
                'first_name' => $item['first_name'],
                'last_name' => $item['last_name'],
                'email' => $item['email'] ?? null,
                'phone' => $item['phone'] ?? null,
                'is_primary' => $item['is_primary'],
            ];
        }
        return $guestsData;
    }

    private function processServices(array $data, int $nights, int $persons): array
    {
        $pivotData = [];
        $totalPrice = 0;
        foreach ($data as $item) {
            $service = Service::find($item['id']);
            $finalPrice = match ($service->price_type) {
                ServiceTypeEnum::PER_STAY => $service->price,
                ServiceTypeEnum::PER_NIGHT => $service->price * $nights,
                ServiceTypeEnum::PER_PERSON => $service->price * $persons,
                ServiceTypeEnum::PER_PERSON_PER_NIGHT => $service->price * $persons * $nights,
                default => $service->price,
            };
            $pivotData[$service->id] = [
                'quantity' => $item['quantity'],
                'price_at_booking' => $finalPrice
            ];
            $totalPrice += $finalPrice * $item['quantity'];
        }
        return [
            'pivot_data' => $pivotData,
            'total_price' => $totalPrice
        ];
    }

    public function updateBooking(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data) {
            if (isset($data['room_category_id']) || isset($data['check_in_date'])) {
                RoomCategory::where('id', $data['room_category_id'] ?? $booking->room_category_id)
                    ->lockForUpdate()
                    ->first();
            }
            $checkIn = $data['check_in_date'] ?? $booking->getRawOriginal('check_in_date');
            $checkOut = $data['check_out_date'] ?? $booking->getRawOriginal('check_out_date');
            $categoryId = $data['room_category_id'] ?? $booking->room_category_id;
            $ratePlanId = $data['rate_plan_id'] ?? $booking->rate_plan_id;
            if (array_intersect_key($data, array_flip(['check_in_date', 'check_out_date', 'rate_plan_id', 'room_category_id']))) {
                $booking->room_price_at_booking = $this->calculateRoomPrice($categoryId, $ratePlanId, $checkIn, $checkOut);
            }
            if (isset($data['guests'])) {
                $guestsData = $this->processGuests($data['guests']);
                $booking->guests()->sync($guestsData);
            }
            if (isset($data['services'])) {
                $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
                $persons = ($data['adults_count'] ?? $booking->adults_count) + ($data['children_count'] ?? $booking->children_count);

                $servicesData = $this->processServices($data['services'], $nights, $persons);
                $booking->services()->sync($servicesData['pivot_data']);

                $totalServicesPrice = $servicesData['total_price'];
            } else {
                $totalServicesPrice = $booking->services->sum(fn($s) => $s->pivot->price_at_booking * $s->pivot->quantity);
            }

            $booking->total_price = $booking->room_price_at_booking + $totalServicesPrice;
            $booking->update($data);

            return $booking->load(['guests', 'services', 'room', 'category', 'plan']);
        });
    }

    public function deleteBooking(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $booking->guests()->detach();
            $booking->services()->detach();

            $booking->delete();
        });
    }

    public function calculateRoomPrice(int $categoryId, int $ratePlanId, string $checkIn, string $checkOut): float|null
    {
        $totalPrice = 0;
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        foreach ($period as $date) {
            $pricePerNight = $this->calculatePricePerNight($ratePlanId, $categoryId, $date->format('Y-m-d'));
            if ($pricePerNight === null) {
                return null;
            }
            $totalPrice += $pricePerNight;
        }
        return $totalPrice;
    }

    private function calculatePricePerNight(int $ratePlanId, int $categoryId, string $date): float|null
    {
        static $plansCache = [];
        if (!isset($plansCache[$ratePlanId])) {
            $plansCache[$ratePlanId] = RatePlan::with(['overrides', 'prices'])->find($ratePlanId);
        }
        $ratePlan = $plansCache[$ratePlanId];
        $override = $ratePlan->overrides
            ->where('room_category_id', $categoryId)
            ->where('date', $date)
            ->first();
        if ($override?->override_price !== null) {
            return (float) $override->override_price;
        }
        $basePrice = $ratePlan->prices
            ->where('room_category_id', $categoryId)
            ->where('date', $date)
            ->first();
        if ($basePrice?->price !== null) {
            return (float) $basePrice->price;
        }
        if ($ratePlan->parent_id !== null) {
            $parentPrice = $this->calculatePricePerNight($ratePlan->parent_id, $categoryId, $date);
            if ($parentPrice !== null) {
                return $this->applyPriceModifier($parentPrice, 'percent', $ratePlan->modifier_percent);
            }
        }
        return null;
    }

    private function applyPriceModifier(float $price, string $type, float $value): float
    {
        if ($type === 'fixed') {
            return $price + $value;
        }
        if ($type === 'percent') {
            return $price + ($price * ($value / 100));
        }
        return $price;
    }

    public function isRoomAvailable(int $categoryId, ?int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        if ($roomId) {
            $isOccupied = Booking::where('room_id', $roomId)
                ->when($excludeBookingId, fn($query) => $query->where('id', '!=', $excludeBookingId))
                ->whereHas('status', function ($query) {
                    $query->whereNotIn('slug', [BookingStatusEnum::CANCELLED->value, BookingStatusEnum::NO_SHOW->value]);
                })
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where('check_in_date', '<', $checkOut)
                        ->where('check_out_date', '>', $checkIn);
                })
                ->exists();
            if ($isOccupied) {
                return false;
            }
        }

        return $this->countAvailableRooms($categoryId, $checkIn, $checkOut, $excludeBookingId) > 0;
    }

    public function countAvailableRooms(int $categoryId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): int
    {
        $allRoomIds = Room::where('room_category_id', $categoryId)->pluck('id');
        $occupiedRoomIds = Booking::where('room_category_id', $categoryId)
            ->whereNotNull('room_id')
            ->when($excludeBookingId, fn($query) => $query->where('id', '!=', $excludeBookingId))
            ->whereHas('status', function ($query) {
                $query->whereNotIn('slug', [BookingStatusEnum::CANCELLED->value, BookingStatusEnum::NO_SHOW->value]);
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->pluck('room_id')
            ->unique();
        $remainingRoomsCount = $allRoomIds->diff($occupiedRoomIds)->count();
        $softBookingsCount = Booking::where('room_category_id', $categoryId)
            ->whereNull('room_id')
            ->when($excludeBookingId, fn($query) => $query->where('id', '!=', $excludeBookingId))
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', [BookingStatusEnum::CANCELLED->value, BookingStatusEnum::NO_SHOW->value]);
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->count();
        $availableRoomsCount = $remainingRoomsCount - $softBookingsCount;
        return $availableRoomsCount > 0 ? $availableRoomsCount : 0;
    }

    public function checkRatePlanRestrictions(int $ratePlanId, int $categoryId, string $checkIn, string $checkOut): string|null
    {
        $ratePlan = RatePlan::findOrFail($ratePlanId);
        if (!$ratePlan->is_active) {
            return "Тарифный план '{$ratePlan->name}' не активен.";
        }
        $now = now()->startOfDay();
        $arrivalDate = Carbon::parse($checkIn)->startOfDay();
        $departureDate = Carbon::parse($checkOut);
        $daysBeforeArrival = $now->diffInDays($arrivalDate);
        $nights = $arrivalDate->diffInDays($departureDate);
        if ($ratePlan->min_days_before_arrival !== null && $daysBeforeArrival < $ratePlan->min_days_before_arrival) {
            return "Этот тариф доступен только при бронировании минимум за {$ratePlan->min_days_before_arrival} дн.";
        }
        if ($ratePlan->max_days_before_arrival !== null && $daysBeforeArrival > $ratePlan->max_days_before_arrival) {
            return "Этот тариф недоступен при бронировании более чем за {$ratePlan->max_days_before_arrival} дн.";
        }
        $ratePlanOverride = $ratePlan->overrides()
            ->where('room_category_id', $categoryId)
            ->where('date', $checkIn)
            ->first();
        $minStay = ($ratePlanOverride && $ratePlanOverride->min_stay !== null)
            ? $ratePlanOverride->min_stay
            : $ratePlan->min_stay_days;
        if ($nights < $minStay) {
            return "Минимальный срок проживания для этого тарифа: {$minStay} н.";
        }
        $period = new DatePeriod(
            new DateTime($checkIn),
            new DateInterval('P1D'),
            new DateTime($checkOut)
        );
        foreach ($period as $date) {
            $currentDate = $date->format('Y-m-d');
            if ($this->isRatePlanClosed($ratePlanId, $categoryId, $currentDate)) {
                return "Продажи закрыты на дату: {$currentDate}";
            }
        }
        return null;
    }

    private function isRatePlanClosed(int $ratePlanId, int $categoryId, string $date): bool
    {
        $ratePlan = RatePlan::find($ratePlanId);
        if (!$ratePlan) {
            return false;
        }
        $override = $ratePlan->overrides()
            ->where('room_category_id', $categoryId)
            ->where('date', $date)
            ->first();
        if ($override && $override->is_closed) {
            return true;
        }
        if ($ratePlan->parent_id) {
            return $this->isRatePlanClosed($ratePlan->parent_id, $categoryId, $date);
        }
        return false;
    }

    public function hasPricesForPeriod(int $categoryId, int $ratePlanId, string $checkIn, string $checkOut): bool
    {
        $period = new DatePeriod(
            new DateTime($checkIn),
            new DateInterval('P1D'),
            new DateTime($checkOut)
        );
        foreach ($period as $date) {
            $hasPricePerNight = $this->hasPricePerNight($ratePlanId, $categoryId, $date->format('Y-m-d'));
            if (!$hasPricePerNight) {
                return false;
            }
        }
        return true;
    }

    private function hasPricePerNight(int $ratePlanId, int $categoryId, string $date): bool
    {
        $ratePlan = RatePlan::find($ratePlanId);
        if (!$ratePlan) {
            return false;
        }
        $override = $ratePlan->overrides()
            ->where('room_category_id', $categoryId)
            ->where('date', $date)
            ->first();
        if ($override && $override->override_price !== null) {
            return true;
        }
        $basePrice = $ratePlan->prices()
            ->where('room_category_id', $categoryId)
            ->where('date', $date)
            ->first();
        if ($basePrice && $basePrice->price !== null) {
            return true;
        }
        if ($ratePlan->parent_id) {
            $hasParentPrice = $this->hasPricePerNight($ratePlan->parent_id, $categoryId, $date);
            if ($hasParentPrice) {
                return true;
            }
        }
        return false;
    }
}
