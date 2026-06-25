<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\UpdateBookingStayDetailsRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Support\Facades\Gate;

class BookingStayDetailsController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function update(UpdateBookingStayDetailsRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);

        $booking = $this->reservationService->updateStayDetails(
            $booking,
            $request->validated()
        );

        return new BookingResource($booking);
    }
}