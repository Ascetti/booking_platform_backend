<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotels = Hotel::all();

        foreach ($hotels as $hotel) {
            // Создаём 4 услуги для каждого отеля
            Service::factory(4)->create(['hotel_id' => $hotel->id]);
        }
    }
}
