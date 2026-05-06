<?php

namespace App\Services\Hotel;

use App\Models\Hotel;

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
}