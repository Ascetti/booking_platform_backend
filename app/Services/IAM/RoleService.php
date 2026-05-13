<?php

namespace App\Services\IAM;

use App\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function storeRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(Arr::except($data, ['permissions']));

            if (!empty($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            return $role;
        });
    }

    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update(Arr::except($data, ['permissions']));

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            return $role;
        });
    }

    public function deleteRole(Role $role): bool
    {
        return $role->delete();
    }
}
