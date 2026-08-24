<?php

namespace Tests\Unit;

use App\Events\BadgeUnlocked;
use App\Listeners\ProcessCashback;

use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CashbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that unlocking a badge sends the configured cashback amount to the user.
     */
    public function test_badge_unlock_sends_configured_cashback(): void
    {
        $user = User::factory()->create();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->with(
                $user->id,
                30000,
                Mockery::type('string')
            );

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService()
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );

        $this->assertTrue(true);
    }

    /**
     * Verify that changing the stored cashback setting changes the amount sent to the user.
     */
    public function test_cashback_amount_comes_from_settings(): void
    {
        $user = User::factory()->create();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '50000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->with(
                $user->id,
                50000,
                Mockery::type('string')
            );

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService()
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );

        $this->assertTrue(true);
    }

    /**
     * Verify that the listener generates a unique-looking reference containing the user and badge.
     */
    public function test_cashback_reference_contains_user_and_badge(): void
    {
        $user = User::factory()->create();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (
                int $userId,
                int $amount,
                string $reference
            ) use ($user): bool {
                return $userId === $user->id
                    && $amount === 30000
                    && str_contains($reference, "cashback-{$user->id}-")
                    && str_contains($reference, 'starter');
            });

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService()
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );

        $this->assertTrue(true);
    }

    /**
     * Verify that a missing cashback setting safely falls back to zero instead of inventing an amount.
     */
    public function test_missing_cashback_setting_defaults_to_zero(): void
    {
        $user = User::factory()->create();

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->with(
                $user->id,
                0,
                Mockery::type('string')
            );

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService()
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );

        $this->assertTrue(true);
    }
}