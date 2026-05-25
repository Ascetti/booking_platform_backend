<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Room\StoreRoomRequest;
use App\Http\Requests\Api\V1\Room\UpdateRoomRequest;
use App\Http\Resources\Api\V1\RoomResource;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Hotel\RoomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    public function __construct(
        protected RoomService $roomService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(RoomCategory $roomCategory)
    {
        Gate::authorize('viewAny', [Room::class, $roomCategory]);
        $rooms = $roomCategory->rooms()->paginate(20);
        return RoomResource::collection($rooms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request, RoomCategory $roomCategory)
    {
        Gate::authorize('create', [Room::class, $roomCategory]);
        $data = $request->validated();
        $room = $this->roomService->createRoom($roomCategory, $data);
        return RoomResource::collection($room);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        Gate::authorize('view', $room);
        return new RoomResource($room);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, Room $room)
    {
        Gate::authorize('update', $room);
        $data = $request->validated();
        $room = $this->roomService->updateRoom($room, $data);
        return new RoomResource($room);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        Gate::authorize('delete', $room);
        $this->roomService->deleteRoom($room);
        return response()->noContent();
    }
}
