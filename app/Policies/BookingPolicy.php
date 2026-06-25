<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Hotel $hotel): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::BOOKINGS_VIEW);
        }
        return $user->hasHotelPermission(PermissionEnum::BOOKINGS_VIEW, $hotel->id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::BOOKINGS_VIEW);
        }
        return $user->hasHotelPermission(PermissionEnum::BOOKINGS_VIEW, $booking->hotel_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Hotel $hotel): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::BOOKINGS_MANAGE);
        }
        return $user->hasHotelPermission(PermissionEnum::BOOKINGS_MANAGE, $hotel->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Booking $booking): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::BOOKINGS_MANAGE);
        }
        return $user->hasHotelPermission(PermissionEnum::BOOKINGS_MANAGE, $booking->hotel_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::BOOKINGS_MANAGE);
        }
        return $user->hasHotelPermission(PermissionEnum::BOOKINGS_MANAGE, $booking->hotel_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }
}
