<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // A payout destination for every factory-made user, so the
            // cashback chain is exercisable end to end (in tests and in the
            // seeded demo data) without a separate account-onboarding step.
            // Bank code 044 (Access Bank) + a fake 10-digit NUBAN is enough
            // for Paystack's test-mode transfer recipient endpoint; it's
            // meaningless once real (non-test) Paystack keys are used.
            'bank_account_number' => fake()->numerify('##########'),
            'bank_code' => '044',
            'bank_account_name' => $name,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
