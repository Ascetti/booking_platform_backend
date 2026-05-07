<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function createRoom(Hotel $hotel, array $data): Room
    {
        return $hotel->rooms()->create($data);
    }

    public function bulkCreateRooms(Hotel $hotel, int $categoryId, array $roomNames): Collection
    {
        return DB::transaction(function () use ($hotel, $categoryId, $roomNames) {
            $rooms = collect();

            foreach ($roomNames as $name) {
                $rooms->push($hotel->rooms()->create([
                    'room_category_id' => $categoryId,
                    'name' => $name,
                ]));
            }

            return $rooms;
        });
    }

    public function updateRoom(Room $room, array $data): Room
    {
        $room->update($data);
        return $room;
    }

    public function deleteRoom(Room $room): bool
    {
        return $room->delete();
    }
}