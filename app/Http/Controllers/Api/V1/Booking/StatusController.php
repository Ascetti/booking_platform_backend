<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\UpdateStatusRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class StatusController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function update(UpdateStatusRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);

        $status = BookingStatusEnum::from($request->validated()['status']);

        $booking = match($status) {
            BookingStatusEnum::NEW        => $this->reservationService->restore($booking),
            BookingStatusEnum::CONFIRMED  => $this->reservationService->confirm($booking),
            BookingStatusEnum::CANCELLED  => $this->reservationService->cancel($booking),
            BookingStatusEnum::CHECKED_IN => $this->reservationService->checkIn($booking),
            BookingStatusEnum::CHECKED_OUT => $this->reservationService->checkOut($booking),
            default => throw new UnprocessableEntityHttpException(
                "Cannot manually set status to '{$status->value}'."
            ),
        };

        return new BookingResource($booking);
    }
}