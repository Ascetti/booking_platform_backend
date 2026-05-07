<?php

namespace App\Services\Hotel;

use App\Models\Amenity;

class AmenityService
{
    public function createAmenity(array $data): Amenity
    {
        return Amenity::create($data);
    }

    public function updateAmenity(Amenity $amenity, array $data): Amenity
    {
        $amenity->update($data);
        return $amenity;
    }

    public function deleteAmenity(Amenity $amenity): bool
    {
        return $amenity->delete();
    }
}