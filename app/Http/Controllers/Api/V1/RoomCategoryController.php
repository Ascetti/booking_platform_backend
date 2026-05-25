<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RoomCategory\StoreRoomCategoryRequest;
use App\Http\Requests\Api\V1\RoomCategory\UpdateRoomCategoryRequest;
use App\Http\Resources\Api\V1\RoomCategoryResource;
use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Services\Hotel\RoomCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomCategoryController extends Controller
{
    public function __construct(
        protected RoomCategoryService $roomCategoryService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Hotel $hotel)
    {
        Gate::authorize('viewAny', [RoomCategory::class, $hotel]);
        $categories = $hotel->categories()
            ->with(['amenities', 'media', 'rooms'])
            ->withCount('rooms')
            ->get();
        return RoomCategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomCategoryRequest $request, Hotel $hotel)
    {
        Gate::authorize('create', [RoomCategory::class, $hotel]);
        $data = $request->validated();
        $category = $this->roomCategoryService->createCategory($hotel, $data);
        return new RoomCategoryResource($category->load(['amenities', 'media']));
    }

    /**
     * Display the specified resource.
     */
    public function show(RoomCategory $roomCategory)
    {
        Gate::authorize('view', $roomCategory);
        return new RoomCategoryResource($roomCategory->load(['amenities', 'media', 'rooms'])->loadCount('rooms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomCategoryRequest $request, RoomCategory $roomCategory)
    {
        Gate::authorize('update', $roomCategory);
        $data = $request->validated();
        $category = $this->roomCategoryService->updateCategory($roomCategory, $data);
        return new RoomCategoryResource($category->load(['amenities', 'media']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RoomCategory $roomCategory)
    {
        Gate::authorize('delete', $roomCategory);
        $this->roomCategoryService->deleteCategory($roomCategory);
        return response()->noContent();
    }
}
