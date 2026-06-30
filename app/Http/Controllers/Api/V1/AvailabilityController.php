<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\GetAvailabilityRequest;
use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Reservation\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    public function index(GetAvailabilityRequest $request, Hotel $hotel)
    {
        Gate::authorize('viewAny', [Booking::class, $hotel]);

        $data = $request->validated();

        $availability = $this->availabilityService->getAvailability(
            hotel: $hotel,
            checkIn: Carbon::parse($data['check_in_date']),
            checkOut: Carbon::parse($data['check_out_date']),
            adults: $data['adults_count'],
            children: $data['children_count'],
            excludeBookingId: $data['exclude_booking_id'] ?? null,
        );

        return response()->json(['data' => $availability]);
    }
}
