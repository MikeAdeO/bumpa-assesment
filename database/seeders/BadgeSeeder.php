<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'name' => 'Starter',
                'description' => 'Unlock 2 achievements.',
                'required_achievements' => 2,
                'sort_order' => 1,
            ],
            [
                'name' => 'Advanced',
                'description' => 'Unlock 3 achievements.',
                'required_achievements' => 3,
                'sort_order' => 2,
            ],
            [
                'name' => 'Expert',
                'description' => 'Unlock all 4 achievements.',
                'required_achievements' => 4,
                'sort_order' => 3,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['name' => $badge['name']],
                [
                    ...$badge,
                    'is_active' => true,
                ]
            );
        }
    }
}