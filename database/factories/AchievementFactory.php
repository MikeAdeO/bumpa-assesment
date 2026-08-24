<?php

namespace Database\Factories;

use App\Models\Achievement;
use App\Models\AchievementGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        return [
            'achievement_group_id' => AchievementGroup::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'rule_type' => 'purchase_count',
            'threshold' => fake()->numberBetween(1, 20),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
