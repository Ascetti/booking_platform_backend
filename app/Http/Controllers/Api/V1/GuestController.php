<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Guest\StoreGuestRequest;
use App\Http\Requests\Api\V1\Guest\UpdateGuestRequest;
use App\Http\Resources\Api\V1\GuestResource;
use App\Models\Guest;
use App\Models\Hotel;
use App\Services\GuestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GuestController extends Controller
{
    public function __construct(
        protected GuestService $guestService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Hotel $hotel)
    {
        Gate::authorize('viewAny', [Guest::class, $hotel]);
        $guests = Guest::whereHas('bookings', function ($query) use ($hotel) {
            $query->where('hotel_id', $hotel->id);
        })
        ->orderBy('last_name')
        ->paginate(20);
        return GuestResource::collection($guests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGuestRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Guest $guest)
    {
        Gate::authorize('view', $guest);
        $guest->loadCount('bookings');
        return new GuestResource($guest);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuestRequest $request, Guest $guest)
    {
        Gate::authorize('update', $guest);
        $data = $request->validated();
        $updatedGuest = $this->guestService->update($guest, $data);
        return new GuestResource($updatedGuest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Guest $guest)
    {
        Gate::authorize('delete', $guest);
        $this->guestService->delete($guest);
        return response()->noContent();
    }
}
