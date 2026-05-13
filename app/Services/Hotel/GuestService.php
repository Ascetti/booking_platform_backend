<?php

namespace App\Services;

use App\Models\Guest;
use Illuminate\Support\Facades\DB;

class GuestService
{
    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($data);
        return $guest;
    }

    public function delete(Guest $guest): bool
    {
        return DB::transaction(function () use ($guest) {
            return $guest->delete();
        });
    }
}