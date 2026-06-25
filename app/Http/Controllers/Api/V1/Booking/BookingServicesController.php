<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\UpdateBookingServicesRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Support\Facades\Gate;

class BookingServicesController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function update(UpdateBookingServicesRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);

        $booking = $this->reservationService->updateServices(
            $booking,
            $request->validated()
        );

        return new BookingResource($booking);
    }
}