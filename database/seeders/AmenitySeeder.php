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
            ['name' => 'Кондиционер', 'slug' => 'conditioner', 'icon_src' => 'wind'],
            ['name' => 'Телефизор', 'slug' => 'tv', 'icon_src' => 'tv'],
            ['name' => 'Мини-бар', 'slug' => 'mini_bar', 'icon_src' => 'beaker'],
            ['name' => 'Фен', 'slug' => 'hair_dryer', 'icon_src' => 'scissors'],
            ['name' => 'Рабочее место', 'slug' => 'workspace', 'icon_src' => 'briefcase'],
            ['name' => 'Сейф', 'slug' => 'safe', 'icon_src' => 'lock-closed'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}
