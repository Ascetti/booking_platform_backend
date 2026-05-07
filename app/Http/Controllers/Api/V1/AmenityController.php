<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Http\Resources\Api\V1\AmenityResource;
use App\Http\Requests\Api\V1\Amenity\StoreAmenityRequest;
use App\Http\Requests\Api\V1\Amenity\UpdateAmenityRequest;
use App\Services\Hotel\AmenityService;
use Illuminate\Support\Facades\Gate;

class AmenityController extends Controller
{
    public function __construct(
        protected AmenityService $amenityService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Amenity::class);
        $amenities = Amenity::all();
        return AmenityResource::collection($amenities);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAmenityRequest $request)
    {
        Gate::authorize('create', Amenity::class);
        $data = $request->validated();
        $amenity = $this->amenityService->createAmenity($data);
        return new AmenityResource($amenity);
    }

    /**
     * Display the specified resource.
     */
    public function show(Amenity $amenity)
    {
        Gate::authorize('view', $amenity);
        return new AmenityResource($amenity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAmenityRequest $request, Amenity $amenity)
    {
        Gate::authorize('update', $amenity);   
        $data = $request->validated();
        $amenity = $this->amenityService->updateAmenity($amenity, $data);
        return new AmenityResource($amenity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Amenity $amenity)
    {
        Gate::authorize('delete', $amenity);
        $this->amenityService->deleteAmenity($amenity);
        return response()->noContent();
    }
}
