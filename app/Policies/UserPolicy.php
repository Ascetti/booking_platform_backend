<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::USERS_VIEW);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasGlobalPermission(PermissionEnum::USERS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::USERS_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasGlobalPermission(PermissionEnum::USERS_EDIT);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasGlobalPermission(PermissionEnum::USERS_DELETE);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
