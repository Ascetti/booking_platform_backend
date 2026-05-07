<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            ['name' => 'Wi-Fi', 'icon_src' => 'wifi'],
            ['name' => 'Swimming Pool', 'icon_src' => 'swimming-pool'],
            ['name' => 'Parking', 'icon_src' => 'truck'],
            ['name' => 'Conditioner', 'icon_src' => 'wind'],
            ['name' => 'Breakfast', 'icon_src' => 'cake'],
            ['name' => 'TV', 'icon_src' => 'tv'],
            ['name' => 'Mini-bar', 'icon_src' => 'beaker'],
            ['name' => 'Hair dryer', 'icon_src' => 'scissors'],
            ['name' => 'Workspace', 'icon_src' => 'briefcase'],
            ['name' => 'Safe', 'icon_src' => 'lock-closed'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}
