<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\BookingStatus;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingStatusPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::STATUSES_VIEW);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BookingStatus $bookingStatus): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::STATUSES_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::STATUSES_MANAGE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BookingStatus $bookingStatus): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::STATUSES_MANAGE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BookingStatus $bookingStatus): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::STATUSES_MANAGE);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BookingStatus $bookingStatus): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BookingStatus $bookingStatus): bool
    {
        return false;
    }
}
