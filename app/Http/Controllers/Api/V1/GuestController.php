<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Guest\StoreGuestRequest;
use App\Http\Requests\Api\V1\Guest\UpdateGuestRequest;
use App\Http\Resources\Api\V1\GuestResource;
use App\Models\Guest;
use App\Models\Hotel;
use App\Services\Reservation\GuestService;
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
    public function index(Request $request, Hotel $hotel)
    {
        Gate::authorize('viewAny', [Guest::class, $hotel]);

        $query = $hotel->guests()->orderBy('last_name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $guests = $query->with(['bookings'])->paginate($request->input('per_page', 6));

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
        return new GuestResource(
            $guest->load('bookings')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuestRequest $request, Guest $guest)
    {
        Gate::authorize('update', $guest);
        $guest = $this->guestService->update($guest, $request->validated());
        return new GuestResource($guest);
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
