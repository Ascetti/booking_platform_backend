<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotelA = Hotel::factory()->create(['name' => 'Alfa Hotel']);
        $hotelB = Hotel::factory()->create(['name' => 'Beta Hotel']);
        $hotelC = Hotel::factory()->create(['name' => 'Gamma Hotel']);
    }
}
