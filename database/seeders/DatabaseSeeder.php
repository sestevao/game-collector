<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Platform;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $platforms = [
            ['name' => 'PlayStation 5', 'slug' => 'ps5'],
            ['name' => 'PlayStation 4', 'slug' => 'ps4'],
            ['name' => 'PlayStation 3', 'slug' => 'ps3'],
            ['name' => 'PlayStation 2', 'slug' => 'ps2'],
            ['name' => 'PlayStation', 'slug' => 'ps1'],
            ['name' => 'PS Vita', 'slug' => 'ps-vita'],
            ['name' => 'Xbox Series S/X', 'slug' => 'xbox-series-x'],
            ['name' => 'Xbox 360', 'slug' => 'xbox-360'],
            ['name' => 'PC', 'slug' => 'pc'],
            ['name' => 'Nintendo DS', 'slug' => 'nintendo-ds'],
            ['name' => 'Nintendo GameCube', 'slug' => 'nintendo-gamecube'],
            ['name' => 'Wii', 'slug' => 'wii'],
            ['name' => 'Wii U', 'slug' => 'wii-u'],
            ['name' => 'Nintendo Switch', 'slug' => 'switch'],
        ];

        foreach ($platforms as $platform) {
            Platform::firstOrCreate(['slug' => $platform['slug']], $platform);
        }
    }
}
