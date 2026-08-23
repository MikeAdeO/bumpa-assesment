<?php

namespace Database\Seeders;

use App\Enums\AchievementRuleType;
use App\Models\Achievement;
use App\Models\AchievementGroup;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $group = AchievementGroup::updateOrCreate(
            ['name' => 'Purchases'],
            [
                'description' => 'Achievements earned by making purchases.',
                'is_active' => true,
            ]
        );

        $achievements = [
            [
                'name' => 'First Purchase',
                'description' => 'Make your first purchase.',
                'rule_type' => AchievementRuleType::FirstPurchase,
                'threshold' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '5 Purchases',
                'description' => 'Complete 5 purchases.',
                'rule_type' => AchievementRuleType::PurchaseCount,
                'threshold' => 5,
                'sort_order' => 2,
            ],
            [
                'name' => '10 Purchases',
                'description' => 'Complete 10 purchases.',
                'rule_type' => AchievementRuleType::PurchaseCount,
                'threshold' => 10,
                'sort_order' => 3,
            ],
            [
                'name' => '20 Purchases',
                'description' => 'Complete 20 purchases.',
                'rule_type' => AchievementRuleType::PurchaseCount,
                'threshold' => 20,
                'sort_order' => 4,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                [
                    'achievement_group_id' => $group->id,
                    'name' => $achievement['name'],
                ],
                [
                    ...$achievement,
                    'is_active' => true,
                ]
            );
        }
    }
}