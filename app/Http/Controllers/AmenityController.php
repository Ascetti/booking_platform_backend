<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Http\Requests\StoreAmenityRequest;
use App\Http\Requests\UpdateAmenityRequest;
use App\Http\Resources\AmenityResource;

class AmenityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $amenities = Amenity::all();
        return AmenityResource::collection($amenities);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAmenityRequest $request)
    {
        $data = $request->validated();
        $amenity = Amenity::create($data);
        return new AmenityResource($amenity);
    }

    /**
     * Display the specified resource.
     */
    public function show(Amenity $amenity)
    {
        return new AmenityResource($amenity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAmenityRequest $request, Amenity $amenity)
    {
        $data = $request->validated();
        $amenity->update($data);
        return new AmenityResource($amenity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Amenity $amenity)
    {
        $amenity->delete();
        return response()->noContent();
    }
}
