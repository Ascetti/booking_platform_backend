<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Enums\BookingStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingAvailableRoomsController extends Controller
{
    public function index(Booking $booking)
    {
        Gate::authorize('view', $booking);

        $activeStatuses = [
            BookingStatusEnum::NEW->value,
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::CHECKED_IN->value,
        ];

        // Берём занятые номера на даты этого бронирования
        // Исключаем само бронирование — иначе его текущий номер тоже будет занятым
        $occupiedRoomIds = Booking::where('id', '!=', $booking->id)
            ->whereNotNull('room_id')
            ->whereHas('status', fn($q) => $q->whereIn('slug', $activeStatuses))
            ->where('check_in_date', '<', $booking->check_out_date)
            ->where('check_out_date', '>', $booking->check_in_date)
            ->pluck('room_id');

        // Свободные активные номера категории этого бронирования
        $rooms = $booking->category->rooms()
            ->where('is_active', true)
            ->whereNotIn('id', $occupiedRoomIds)
            ->get(['id', 'name']);

        return response()->json(['data' => $rooms]);
    }
}
