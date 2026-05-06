<?php

namespace App\Services\IAM;

use App\Enums\RoleEnum;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function assignRole(int $userId, int $roleId, ?int $hotelId = null): void
    {
        $user = User::findOrFail($userId);

        $user->roles()->attach([
            $roleId => ['hotel_id' => $hotelId]
        ]);
    }

    public function removeRole(int $userId, int $roleId, ?int $hotelId = null): void
    {
        $user = User::findOrFail($userId);
        
        $user->roles()
            ->wherePivot('role_id', $roleId)
            ->wherePivot('hotel_id', $hotelId)
            ->detach();
    }

    public function syncHotelStaff(int $hotelId, array $staffData): void
    {
        // $staffData — это массив вида: [['user_id' => 1, 'role_id' => 2], ...]

        DB::transaction(function () use ($hotelId, $staffData) {
            $hotel = Hotel::findOrFail($hotelId);
            $syncPayload = [];
            foreach ($staffData as $item) {
                $syncPayload[$item['user_id']] = [
                    'role_id' => $item['role_id']
                ];
            }

            $hotel->users()->sync($syncPayload);
        });
    }
}