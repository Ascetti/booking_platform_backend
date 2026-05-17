<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookingStatus\StoreBookingStatusRequest;
use App\Http\Requests\Api\V1\BookingStatus\UpdateBookingStatusRequest;
use App\Http\Resources\Api\V1\BookingStatusResource;
use App\Models\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', BookingStatus::class);
        $statuses = BookingStatus::all();
        return BookingStatusResource::collection($statuses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookingStatusRequest $request)
    {
        Gate::authorize('create', BookingStatus::class);
        $data = $request->validated();
        $status = BookingStatus::create($data);
        return new BookingStatusResource($status);
    }

    /**
     * Display the specified resource.
     */
    public function show(UpdateBookingStatusRequest $bookingStatus)
    {
        Gate::authorize('view', $bookingStatus);
        return new BookingStatusResource($bookingStatus);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BookingStatus $bookingStatus)
    {
        Gate::authorize('update', $bookingStatus);
        $data = $request->validated();
        $bookingStatus->update($data);
        return new BookingStatusResource($bookingStatus);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BookingStatus $bookingStatus)
    {
        Gate::authorize('delete', $bookingStatus);
        if ($bookingStatus->bookings()->exists()) {
            return response([
                'message' => 'Cannot delete status that is assigned to bookings.'
            ], 422);
        }
        $bookingStatus->delete();
        return response()->noContent();
    }
}
