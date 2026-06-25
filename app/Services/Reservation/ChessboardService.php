<?php

namespace App\Services\Reservation;

use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Enums\BookingStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChessboardService
{
    /**
     * Получить данные для шахматки.
     */
    public function getChessboard(Hotel $hotel, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Активные статусы — только их показываем на шахматке
        $activeStatuses = [
            BookingStatusEnum::NEW->value,
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::CHECKED_IN->value,
        ];

        // Берём все категории отеля с номерами
        $categories = RoomCategory::where('hotel_id', $hotel->id)
            ->with(['rooms' => function ($query) {
                $query->orderBy('name');
            }])
            ->get();

        $result = [];

        foreach ($categories as $category) {
            // Берём все бронирования категории за период
            // Пересечение дат: заезд < dateTo И выезд > dateFrom
            $bookings = $category->bookings()
                ->whereHas('status', fn($q) => $q->whereIn('slug', $activeStatuses))
                ->where('check_in_date', '<', $dateTo)
                ->where('check_out_date', '>', $dateFrom)
                ->with(['status', 'guests'])
                ->get();

            // Бронирования без номера — висят на категории
            $unassignedBookings = $bookings
                ->whereNull('room_id')
                ->map(fn($b) => $this->formatBooking($b))
                ->values();

            // Бронирования назначенные на номера
            $assignedBookings = $bookings->whereNotNull('room_id')->groupBy('room_id');

            // Формируем строки номеров
            $rooms = $category->rooms->map(function ($room) use ($assignedBookings) {
                return [
                    'id'        => $room->id,
                    'name'      => $room->name,
                    'is_active' => $room->is_active,
                    'bookings'  => $assignedBookings->has($room->id)
                        ? $assignedBookings->get($room->id)
                            ->map(fn($b) => $this->formatBooking($b))
                            ->values()
                        : [],
                ];
            });

            $result[] = [
                'id'                   => $category->id,
                'name'                 => $category->name,
                'unassigned_bookings'  => $unassignedBookings,
                'rooms'                => $rooms,
            ];
        }

        return $result;
    }

    /**
     * Форматирует бронирование для шахматки.
     */
    private function formatBooking($booking): array
    {
        // Берём первичного гостя из снимка (pivot)
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
            'guest'          => $primaryGuest ? [
                'first_name' => $primaryGuest->pivot->first_name,
                'last_name'  => $primaryGuest->pivot->last_name,
                'email'      => $primaryGuest->pivot->email,
                'phone'      => $primaryGuest->pivot->phone,
            ] : null,
        ];
    }
}