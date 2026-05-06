<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hotel\StoreHotelRequest;
use App\Http\Requests\Api\V1\Hotel\UpdateHotelRequest;
use App\Http\Resources\Api\V1\HotelResource;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use App\Services\Hotel\HotelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HotelController extends Controller
{
    public function __construct(
        protected HotelService $hotelService
    ) {}
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Hotel::class);
        $hotels = Hotel::accessibleBy($request->user())->get();
        return HotelResource::collection($hotels);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotelRequest $request)
    {
        Gate::authorize('create', Hotel::class);
        $data = $request->validated();
        $hotel = $this->hotelService->createHotel($data);
        return new HotelResource($hotel);
    }

    /**
     * Display the specified resource.
     */
    public function show(Hotel $hotel)
    {
        Gate::authorize('view', $hotel);
        return new HotelResource($hotel);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHotelRequest $request, Hotel $hotel)
    {
        Gate::authorize('update', $hotel);
        $data = $request->validated();
        $hotel = $this->hotelService->updateHotel($hotel, $data);
        return new HotelResource($hotel);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Hotel $hotel)
    {
        Gate::authorize('delete', $hotel);
        $hotel->delete();
        return response()->noContent();
    }
}
