<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoomCategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Hotel $hotel): bool
    {
        return $user->hasHotelPermission(PermissionEnum::CATEGORIES_VIEW, $hotel->id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RoomCategory $roomCategory): bool
    {
        return $user->hasHotelPermission(PermissionEnum::CATEGORIES_VIEW, $roomCategory->hotel_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Hotel $hotel): bool
    {
        return $user->hasHotelPermission(PermissionEnum::CATEGORIES_MANAGE, $hotel->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RoomCategory $roomCategory): bool
    {
        return $user->hasHotelPermission(PermissionEnum::CATEGORIES_MANAGE, $roomCategory->hotel_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RoomCategory $roomCategory): bool
    {
        return $user->hasHotelPermission(PermissionEnum::CATEGORIES_MANAGE, $roomCategory->hotel_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RoomCategory $roomCategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RoomCategory $roomCategory): bool
    {
        return false;
    }
}
