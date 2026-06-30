<?php

namespace App\Services\Hotel;

use App\Enums\BookingStatusEnum;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoomService
{
    public function createRoom(RoomCategory $category, array $data): Collection
    {
        if (isset($data['names']) && is_array($data['names'])) {
            return $this->bulkCreateRooms($category, $data['names'], $data['is_active'] ?? true);
        }

        return collect([
            $category->rooms()->create([
                'name'      => $data['name'],
                'is_active' => $data['is_active'] ?? true,
            ])
        ]);
    }

    private function bulkCreateRooms(RoomCategory $category, array $roomNames, bool $isActive = true): Collection
    {
        return DB::transaction(function () use ($category, $roomNames, $isActive) {
            $rooms = collect();
            foreach ($roomNames as $name) {
                $rooms->push($category->rooms()->create([
                    'name'      => $name,
                    'is_active' => $isActive,
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
        if ($room->bookings()->whereHas('status', fn($q) => $q->whereNotIn('slug', [
            BookingStatusEnum::CANCELLED->value,
            BookingStatusEnum::CHECKED_OUT->value,
        ]))->exists()) {
            throw new ConflictHttpException('Cannot delete room with active bookings.');
        }

        return $room->delete();
    }
}
