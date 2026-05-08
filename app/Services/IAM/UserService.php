<?php

namespace App\Services\IAM;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function storeUser(array $data): User
    {
        // return DB::transaction(function () use ($data) {
        //     $user = User::create(collect($data)->except(['role_id', 'hotel_id'])->toArray());

        //     $user->roles()->attach($data['role_id'], [
        //         'hotel_id' => $data['hotel_id'] ?? null
        //     ]);

        //     return $user;
        // });
        return User::create($data);
    }

    public function updateUser(User $user, array $data): User
    {
        // return DB::transaction(function () use ($user, $data) {
        //     if (empty($data['password'])) {
        //         unset($data['password']);
        //     }

        //     $user->update(collect($data)->except(['role_id', 'hotel_id'])->toArray());

        //     if (isset($data['role_id'])) {
        //         $user->roles()->syncWithPivotValues($data['role_id'], [
        //             'hotel_id' => $data['hotel_id'] ?? null
        //         ]);
        //     }

        //     return $user;
        // });
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        return $user;
    }

    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }
}
