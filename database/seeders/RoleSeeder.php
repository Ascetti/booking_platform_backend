<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Permission;
use App\Services\IAM\RoleService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allPermissions = Permission::all()->pluck('id')->toArray();    

        $hotelAdminPermissions = Permission::whereIn('name', [
            PermissionEnum::USERS_VIEW->value,
            PermissionEnum::HOTELS_VIEW->value,
            PermissionEnum::HOTELS_EDIT->value,
        ])->pluck('id')->toArray();

        $this->roleService->storeRole([
            'name' => RoleEnum::PLATFORM_ADMIN->value,
            'permissions' => $allPermissions
        ]);

        $this->roleService->storeRole([
            'name' => RoleEnum::HOTEL_ADMIN->value,
            'permissions' => $hotelAdminPermissions
        ]);
    }
}
