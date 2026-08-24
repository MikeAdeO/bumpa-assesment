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
     *
     * Safe to run more than once (e.g. every `docker compose up`): the
     * reference-data seeders below use updateOrCreate/firstOrCreate, and
     * the demo user is looked up by email instead of being created blindly,
     * so repeat runs don't pile up duplicate rows or throw on the unique
     * email constraint.
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

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        if (User::count() < 3) {
            User::factory()->count(2)->create();
        }
    }
}
