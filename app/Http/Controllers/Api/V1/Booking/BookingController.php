<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\StoreBookingRequest;
use App\Http\Requests\Api\V1\Booking\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {} 
    /**
     * Display a listing of the resource.
     */
    public function index(Hotel $hotel)
    {
        Gate::authorize('vewAny', $hotel);
        $bookings = $hotel->bookings()->get();
        return BookingResource::collection($bookings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookingRequest $request, Hotel $hotel)
    {
        Gate::authorize('create', [Booking::class, $hotel]);
        $data = $request->validated();
        $booking = $this->bookingService->createBooking($hotel, $data);
        return new BookingResource($booking);
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);
        return new BookingResource($booking);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);
        $data = $request->validated();
        $booking = $this->bookingService->updateBooking($booking, $data);
        return new BookingResource($booking);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);
        $this->bookingService->deleteBooking($booking);
        return response()->noContent();
    }
}
