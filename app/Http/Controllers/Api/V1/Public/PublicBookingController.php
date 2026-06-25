<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\StorePublicBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Hotel;
use App\Services\Reservation\ReservationService;

class PublicBookingController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function store(StorePublicBookingRequest $request, Hotel $hotel)
    {
        $booking = $this->reservationService->create($hotel, $request->validated());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }
}