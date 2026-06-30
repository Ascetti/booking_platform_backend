<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\UpdateBookingExtraRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingExtraController extends Controller
{
    public function update(UpdateBookingExtraRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);

        $booking->update($request->validated());

        return new BookingResource($booking->load([
            'status',
            'category',
            'room',
            'plan',
            'guests',
            'services',
        ]));
    }
}
