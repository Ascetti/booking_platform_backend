<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\RateOverride;
use App\Models\RatePrice;
use Illuminate\Support\Facades\DB;

class HotelService
{
    public function createHotel(array $data): Hotel
    {
        return Hotel::create($data);
    }

    public function updateHotel(Hotel $hotel, array $data): Hotel
    {
        $hotel->update($data);
        return $hotel;
    }

    public function deleteHotel(Hotel $hotel): bool
    {
        return DB::transaction(function () use ($hotel) {
            RatePrice::whereIn('rate_plan_id', $hotel->plans()->pluck('id'))->delete();
            RateOverride::whereIn('rate_plan_id', $hotel->plans()->pluck('id'))->delete();
            $hotel->categories()->delete();
            $hotel->plans()->delete();
            $hotel->services()->delete();
            $hotel->bookings()->delete();
            return $hotel->delete();
        });
    }
}
