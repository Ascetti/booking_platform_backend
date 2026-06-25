<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class GuestPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Hotel $hotel): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::GUESTS_VIEW);
        }
        return $user->hasHotelPermission(PermissionEnum::GUESTS_VIEW, $hotel->id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Guest $guest): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::GUESTS_VIEW);
        }
        return $user->hasHotelPermission(PermissionEnum::GUESTS_VIEW, $guest->hotel_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Hotel $hotel): bool
    {
        return false;
        // return $user->hasPermission(PermissionEnum::GUESTS_MANAGE, $hotel->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Guest $guest): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::GUESTS_MANAGE);
        }
        return $user->hasHotelPermission(PermissionEnum::GUESTS_MANAGE, $guest->hotel_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Guest $guest): bool
    {
        if ($user->isPlatformStaff()) {
            return $user->hasGlobalPermission(PermissionEnum::GUESTS_MANAGE);
        }
        return $user->hasHotelPermission(PermissionEnum::GUESTS_MANAGE, $guest->hotel_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Guest $guest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Guest $guest): bool
    {
        return false;
    }

    private function isGuestRelatedToUserHotelsAndUserHasPermission(User $user, Guest $guest, PermissionEnum $permission): bool
    {
        $relatedHotelIds = $user->permissions()
            ->where('name', $permission->value)
            ->pluck('hotel_id');

        return DB::table('bookings')
            ->join('booking_guest', 'bookings.id', '=', 'booking_guest.booking_id')
            ->where('booking_guest.guest_id', $guest->id)
            ->whereIn('bookings.hotel_id', $relatedHotelIds)
            ->exists();
    }
}
