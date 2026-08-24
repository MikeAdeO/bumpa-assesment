<?php

namespace Database\Seeders;

use App\Models\User;
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

    $this->call([
        CurrencySeeder::class,
        ProductSeeder::class,
        AchievementSeeder::class,
        BadgeSeeder::class,
        SettingSeeder::class,
    ]);
    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    User::factory()->count(2)->create();
}
}
