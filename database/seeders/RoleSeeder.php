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
            PermissionEnum::USERS_EDIT->value,
            PermissionEnum::HOTELS_VIEW->value,
            PermissionEnum::HOTELS_EDIT->value,
            PermissionEnum::AMENITIES_VIEW->value,
            PermissionEnum::CATEGORIES_VIEW->value,
            PermissionEnum::CATEGORIES_MANAGE->value,
            PermissionEnum::ROOMS_VIEW->value,
            PermissionEnum::ROOMS_MANAGE->value,
            PermissionEnum::MEDIA_VIEW->value,
            PermissionEnum::MEDIA_MANAGE->value,
            PermissionEnum::PLANS_VIEW->value,
            PermissionEnum::PLANS_MANAGE->value,
            PermissionEnum::STATUSES_VIEW->value,
            PermissionEnum::STATUSES_MANAGE->value,
            PermissionEnum::SERVICES_VIEW->value,
            PermissionEnum::SERVICES_MANAGE->value,
            PermissionEnum::GUESTS_VIEW->value,
            PermissionEnum::GUESTS_MANAGE->value,
            PermissionEnum::BOOKINGS_VIEW->value,
            PermissionEnum::BOOKINGS_MANAGE->value,
        ])->pluck('id')->toArray();

        $this->roleService->storeRole([
            'name' => RoleEnum::PLATFORM_ADMIN->value,
            'slug' => RoleEnum::PLATFORM_ADMIN->value,
            'permissions' => $allPermissions
        ]);

        $this->roleService->storeRole([
            'name' => RoleEnum::HOTEL_ADMIN->value,
            'slug' => RoleEnum::HOTEL_ADMIN->value,
            'permissions' => $hotelAdminPermissions
        ]);
    }
}
