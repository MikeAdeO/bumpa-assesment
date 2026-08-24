<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed the application's configurable business settings.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'badge_cashback_amount'],
            ['value' => '30000'] // in kobo
        );
    }
}
