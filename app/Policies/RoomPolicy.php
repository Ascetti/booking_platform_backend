<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoomPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, RoomCategory $category): bool
    {
        return $user->hasHotelPermission(PermissionEnum::ROOMS_VIEW, $category->hotel_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Room $room): bool
    {
        return $user->hasHotelPermission(PermissionEnum::ROOMS_VIEW, $room->category->hotel_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, RoomCategory $category): bool
    {
        return $user->hasHotelPermission(PermissionEnum::ROOMS_MANAGE, $category->hotel_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->hasHotelPermission(PermissionEnum::ROOMS_MANAGE, $room->category->hotel_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->hasHotelPermission(PermissionEnum::ROOMS_MANAGE, $room->category->hotel_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Room $room): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Room $room): bool
    {
        return false;
    }
}
