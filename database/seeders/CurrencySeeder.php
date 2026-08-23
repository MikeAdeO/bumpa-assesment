<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::updateOrCreate(
            ['code' => 'NGN'],
            [
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'minor_unit' => 2,
                'is_active' => true,
            ]
        );
    }
}