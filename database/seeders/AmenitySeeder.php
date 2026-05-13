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
            ['name' => 'Wi-Fi', 'slug' => 'wi_fi', 'icon_src' => 'wifi'],
            ['name' => 'Swimming Pool', 'slug' => 'swimming_pool', 'icon_src' => 'swimming-pool'],
            ['name' => 'Parking', 'slug' => 'parking', 'icon_src' => 'truck'],
            ['name' => 'Conditioner', 'slug' => 'conditioner', 'icon_src' => 'wind'],
            ['name' => 'Breakfast', 'slug' => 'breakfast', 'icon_src' => 'cake'],
            ['name' => 'TV', 'slug' => 'tv', 'icon_src' => 'tv'],
            ['name' => 'Mini-bar', 'slug' => 'mini_bar', 'icon_src' => 'beaker'],
            ['name' => 'Hair dryer', 'slug' => 'hair_dryer', 'icon_src' => 'scissors'],
            ['name' => 'Workspace', 'slug' => 'workspace', 'icon_src' => 'briefcase'],
            ['name' => 'Safe', 'slug' => 'safe', 'icon_src' => 'lock-closed'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}
