<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platformAdmin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@platform.com'
        ]);
        
        $hotelAdmin1 = User::factory()->create(['name' => 'Alpha Admin 1', 'email' => 'a1@platform.com']);
        $hotelAdmin2 = User::factory()->create(['name' => 'Alpha Admin 2', 'email' => 'a2@platform.com']);
        $multiAdmin = User::factory()->create(['name' => 'Multi Admin', 'email' => 'multi@platform.com']);
    }
}
