<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use App\Services\IAM\StaffService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        protected StaffService $staffService
    ) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            AmenitySeeder::class,
            HotelSeeder::class,
        ]);

        $platformAdminRole = Role::where('name', RoleEnum::PLATFORM_ADMIN->value)->first();
        $hotelAdminRole = Role::where('name', RoleEnum::HOTEL_ADMIN->value)->first();

        $hotelA = Hotel::where('name', 'Alfa Hotel')->first();
        $hotelB = Hotel::where('name', 'Beta Hotel')->first();
        $hotelC = Hotel::where('name', 'Gamma Hotel')->first();

        $platformAdmin = User::where('email', 'admin@platform.com')->first();
        $hotelAdmin1 = User::where('email', 'a1@platform.com')->first();
        $hotelAdmin2 = User::where('email', 'a2@platform.com')->first();
        $multiAdmin =User::where('email', 'multi@platform.com')->first();


        $this->staffService->assignRole($platformAdmin->id, $platformAdminRole->id, null);
        $this->staffService->assignRole($hotelAdmin1->id, $hotelAdminRole->id, $hotelA->id);
        $this->staffService->assignRole($hotelAdmin2->id, $hotelAdminRole->id, $hotelA->id);
        $this->staffService->assignRole($multiAdmin->id, $hotelAdminRole->id, $hotelB->id);
        $this->staffService->assignRole($multiAdmin->id, $hotelAdminRole->id, $hotelC->id);
    }
}
