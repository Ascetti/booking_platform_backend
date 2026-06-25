<?php

namespace Database\Seeders;

use App\Enums\BookingStatusEnum;
use App\Models\BookingStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (BookingStatusEnum::cases() as $bookingStatus) {
            BookingStatus::firstOrCreate(['name' => $bookingStatus->label(), 'slug' => $bookingStatus->value]);
        }
    }
}
