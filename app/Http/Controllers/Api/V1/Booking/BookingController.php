<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\GetBookingsRequest;
use App\Http\Requests\Api\V1\Booking\StoreBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function index(GetBookingsRequest $request, Hotel $hotel)
    {
        Gate::authorize('viewAny', [Booking::class, $hotel]);

        $data  = $request->validated();
        $query = $hotel->bookings()
            ->with(['status', 'category', 'room', 'plan', 'guests'])
            ->orderBy('id', 'desc');

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }
                $q->orWhereHas('guests', function ($gq) use ($search) {
                    $gq->where('guests.first_name', 'ilike', "%{$search}%")
                        ->orWhere('guests.last_name', 'ilike', "%{$search}%")
                        ->orWhere('guests.phone', 'ilike', "%{$search}%")
                        ->orWhere('guests.email', 'ilike', "%{$search}%");
                });
            });
        }

        if (!empty($data['statuses'])) {
            $query->whereHas(
                'status',
                fn($q) => $q->whereIn('slug', $data['statuses'])
            );
        }

        if (!empty($data['date_from'])) {
            $query->where('check_out_date', '>', $data['date_from']);
        }

        if (!empty($data['date_to'])) {
            $query->where('check_in_date', '<', $data['date_to']);
        }

        $bookings = $query->paginate($data['per_page'] ?? 6);

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request, Hotel $hotel)
    {
        Gate::authorize('create', [Booking::class, $hotel]);

        $booking = $this->reservationService->create($hotel, $request->validated());

        // return (new BookingResource($booking))
        //     ->response()
        //     ->setStatusCode(201);
        return new BookingResource($booking);
    }

    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);

        return new BookingResource(
            $booking->load(['status', 'category', 'room', 'plan', 'guests', 'services'])
        );
    }

    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);

        $this->reservationService->delete($booking);

        return response()->noContent();
    }
}
