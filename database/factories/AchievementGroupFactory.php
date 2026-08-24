<?php

namespace Database\Factories;

use App\Models\AchievementGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AchievementGroup>
 */
class AchievementGroupFactory extends Factory
{
    protected $model = AchievementGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}