<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
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

    public function index(Request $request, Hotel $hotel)
    {
        Gate::authorize('viewAny', [Booking::class, $hotel]);

        $query = $hotel->bookings()
            ->with(['status', 'category', 'room', 'plan', 'guests'])
            ->orderBy('id', 'desc');

        // Поиск по номеру брони, имени, фамилии, телефону, email гостя
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                // По номеру бронирования
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }
                // По данным гостя
                $q->orWhereHas('guests', function ($gq) use ($search) {
                    $gq->where('booking_guest.first_name', 'ilike', "%{$search}%")
                        ->orWhere('booking_guest.last_name', 'ilike', "%{$search}%")
                        ->orWhere('booking_guest.phone', 'ilike', "%{$search}%")
                        ->orWhere('booking_guest.email', 'ilike', "%{$search}%");
                });
            });
        }

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->whereHas(
                'status',
                fn($q) =>
                $q->where('slug', $request->input('status'))
            );
        }

        // Фильтр по датам — бронирования пересекающиеся с периодом
        if ($request->filled('date_from')) {
            $query->where('check_out_date', '>', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('check_in_date', '<', $request->input('date_to'));
        }

        $bookings = $query->paginate($request->input('per_page', 6));

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
