<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $hotelA = Hotel::factory()->create(['name' => 'Alfa Hotel']);
        // $hotelB = Hotel::factory()->create(['name' => 'Beta Hotel']);
        // $hotelC = Hotel::factory()->create(['name' => 'Gamma Hotel']);

        $hotels = [
            ['name' => 'Alfa Hotel'],
            ['name' => 'Beta Hotel'],
            ['name' => 'Gamma Hotel'],
        ];

        $allAmenities = Amenity::all();

        foreach ($hotels as $hotelData) {
            $hotel = Hotel::factory()->create($hotelData);

            $categories = [
                ['name' => 'Standard'],
                ['name' => 'Deluxe'],
                ['name' => 'Suite'],
            ];

            foreach ($categories as $categoryName) {
                $category = RoomCategory::factory()
                    ->for($hotel)
                    ->create($categoryName);

                $category->amenities()->attach(
                    $allAmenities->random(rand(3, 7))->pluck('id')
                );

                 $floor = rand(1, 5);
                for ($i = 1; $i <= 5; $i++) {
                    if ($i < 10) {
                        Room::factory()
                        ->create([
                            'room_category_id' => $category->id,
                            'name' => "{$floor}0{$i}"
                        ]);
                    }
                    else {
                        Room::factory()
                        ->create([
                            'room_category_id' => $category->id,
                            'name' => "{$floor}{$i}"
                        ]);
                    }   
                }
            }
        }
    }
}
