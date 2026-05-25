<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MediaPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, RoomCategory $category): bool
    {
        return $user->hasHotelPermission(PermissionEnum::MEDIA_VIEW, $category->hotel_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Media $media): bool
    {
        return $user->hasHotelPermission(PermissionEnum::MEDIA_VIEW, $media->category->hotel_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, RoomCategory $category): bool
    {
        return $user->hasHotelPermission(PermissionEnum::MEDIA_MANAGE, $category->hotel_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->hasHotelPermission(PermissionEnum::MEDIA_MANAGE, $media->category->hotel_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }
}
