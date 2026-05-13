<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

class BasePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole(RoleEnum::PLATFORM_ADMIN)) {
            return true;
        }
        
        return null;
    }
}
