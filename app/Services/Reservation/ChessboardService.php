<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Enums\BookingStatusEnum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ChessboardService
{
    public function getChessboard(
        Hotel $hotel,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $search = null,
        array $statuses = [],
    ): array {
        // Статусы которые реально занимают место — всегда фиксированные
        $occupyingStatuses = [
            BookingStatusEnum::NEW->value,
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::CHECKED_IN->value,
        ];

        // Статусы для отображения броней на шахматке — из запроса
        $displayStatuses = empty($statuses) ? [
            BookingStatusEnum::NEW->value,
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::CHECKED_IN->value,
            BookingStatusEnum::CHECKED_OUT->value,
            // BookingStatusEnum::CANCELLED->value,
        ] : $statuses;

        // Шаг 1 — категории с номерами
        $categories = RoomCategory::where('hotel_id', $hotel->id)
            ->with(['rooms' => fn($q) => $q->orderBy('name')])
            ->get();

        $categoryIds = $categories->pluck('id');

        // Шаг 2 — список дат периода
        $dates = collect(iterator_to_array(
            CarbonPeriod::create($dateFrom, $dateTo->copy()->subDay())
        ))->map(fn(Carbon $d) => $d->toDateString());

        // Шаг 3а — все брони за период для подсчёта доступности
        // Только занимающие место статусы, без фильтра поиска, без лишних связей
        $bookingsForAvailability = Booking::whereIn('room_category_id', $categoryIds)
            ->whereHas('status', fn($q) => $q->whereIn('slug', $occupyingStatuses))
            ->where('check_in_date', '<', $dateTo)
            ->where('check_out_date', '>', $dateFrom)
            ->get(['id', 'room_category_id', 'room_id', 'check_in_date', 'check_out_date'])
            ->groupBy('room_category_id');

        // Шаг 3б — брони для отображения
        // Статусы из фильтра, с поиском, со связями
        $bookingsQuery = Booking::whereIn('room_category_id', $categoryIds)
            ->whereHas('status', fn($q) => $q->whereIn('slug', $displayStatuses))
            ->where('check_in_date', '<=', $dateTo)
            ->where('check_out_date', '>=', $dateFrom)
            ->with(['status', 'guests']);

        if ($search) {
            $bookingsQuery->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }
                $q->orWhereHas(
                    'guests',
                    fn($gq) => $gq
                        ->where('guests.first_name', 'ilike', "%{$search}%")
                        ->orWhere('guests.last_name', 'ilike', "%{$search}%")
                        ->orWhere('guests.phone', 'ilike', "%{$search}%")
                        ->orWhere('guests.email', 'ilike', "%{$search}%")
                );
            });
        }

        $bookingsForDisplay = $bookingsQuery->get()->groupBy('room_category_id');

        // Шаг 4 — формируем результат для каждой категории
        $result = [];

        foreach ($categories as $category) {
            $availabilityBookings = $bookingsForAvailability->get($category->id, collect());
            $displayBookings      = $bookingsForDisplay->get($category->id, collect());
            $totalActiveRooms     = $category->rooms->where('is_active', true)->count();

            // Считаем свободные номера для каждого дня
            // Занятыми считаем все брони (назначенные и неназначенные)
            // где заезд <= день и выезд > день
            $availability = $dates->mapWithKeys(function ($date) use ($availabilityBookings, $totalActiveRooms) {
                $occupied = $availabilityBookings->filter(
                    fn($b) => $b->check_in_date->toDateString() <= $date
                        && $b->check_out_date->toDateString() > $date
                )->count();

                return [$date => max(0, $totalActiveRooms - $occupied)];
            });

            // Неназначенные брони — висят на категории
            $unassignedBookings = $displayBookings
                ->whereNull('room_id')
                ->map(fn($b) => $this->formatBooking($b))
                ->values();

            // Назначенные брони группируем по room_id
            $assignedBookings = $displayBookings
                ->whereNotNull('room_id')
                ->groupBy('room_id');

            // Формируем строки номеров
            $rooms = $category->rooms->map(fn($room) => [
                'id'        => $room->id,
                'name'      => $room->name,
                'is_active' => $room->is_active,
                'bookings'  => $assignedBookings->has($room->id)
                    ? $assignedBookings->get($room->id)
                    ->map(fn($b) => $this->formatBooking($b))
                    ->values()
                    : [],
            ]);

            $result[] = [
                'id'                  => $category->id,
                'name'                => $category->name,
                'total_active_rooms'  => $totalActiveRooms,
                'availability'        => $availability,
                'unassigned_bookings' => $unassignedBookings,
                'rooms'               => $rooms,
            ];
        }

        return $result;
    }

    private function formatBooking($booking): array
    {
        $primaryGuest = $booking->guests
            ->first(fn($g) => $g->pivot->is_primary);

        return [
            'id'             => $booking->id,
            'check_in_date'  => $booking->check_in_date->format('Y-m-d'),
            'check_out_date' => $booking->check_out_date->format('Y-m-d'),
            'status'         => $booking->status->slug,
            'adults_count'   => $booking->adults_count,
            'children_count' => $booking->children_count,
            'total_price'    => $booking->total_price,
            'guest' => $primaryGuest ? [
                'first_name' => $primaryGuest->first_name,
                'last_name'  => $primaryGuest->last_name,
                'email'      => $primaryGuest->email,
                'phone'      => $primaryGuest->phone,
            ] : null,
        ];
    }
}
