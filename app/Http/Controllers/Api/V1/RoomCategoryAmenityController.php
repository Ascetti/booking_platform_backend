<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Amenity\UpdateRoomCategoryAmenityRequest;
use App\Http\Resources\Api\V1\AmenityResource;
use App\Models\RoomCategory;
use App\Services\Hotel\RoomCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomCategoryAmenityController extends Controller
{
    public function __construct(
        protected RoomCategoryService $categoryService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(RoomCategory $roomCategory)
    {
        Gate::authorize('view', $roomCategory);
        $amenities = $roomCategory->amenities;
        return AmenityResource::collection($amenities);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomCategoryAmenityRequest $request, RoomCategory $roomCategory)
    {
        Gate::authorize('update', $roomCategory);
        $data = $request->validated();
        $amenities = $this->categoryService->syncAmenities($roomCategory, $data['amenities']);
        return AmenityResource::collection($amenities);
    }
}
