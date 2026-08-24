<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'required_achievements' => fake()->numberBetween(1, 10),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
