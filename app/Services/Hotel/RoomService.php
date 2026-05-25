<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function createRoom(RoomCategory $category, array $data): Collection
    {
        if (isset($data['names']) && is_array($data['names'])) {
            return $this->bulkCreateRooms($category, $data['names']);
        }
        return collect([
            $category->rooms()->create([
                'name' => $data['name'],
            ])
        ]);
    }

    private function bulkCreateRooms(RoomCategory $category, array $roomNames): Collection
    {
        return DB::transaction(function () use ($category, $roomNames) {
            $rooms = collect();
            foreach ($roomNames as $name) {
                $rooms->push($category->rooms()->create([
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
