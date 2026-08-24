<?php

namespace Tests\Unit;

use App\Events\BadgeUnlocked;
use App\Listeners\ProcessCashback;
use App\Models\Badge;
use App\Models\Setting;
use App\Models\User;
use App\Payments\Data\CashbackPayout;
use App\Services\PaymentService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CashbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create the badge required by the cashback tests.
     */
    private function createStarterBadge(): Badge
    {
        return Badge::create([
            'name' => 'Starter',
            'description' => 'Starter badge',
            'required_achievements' => 2,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Verify that unlocking a badge sends the configured cashback amount to the user.
     */
    public function test_badge_unlock_sends_configured_cashback(): void
    {
        $user = User::factory()->create();
        $this->createStarterBadge();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 30000
                    && $payout->currency === 'NGN';
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );
    }

    /**
     * Verify that changing the stored cashback setting changes the amount sent to the user.
     */
    public function test_cashback_amount_comes_from_settings(): void
    {
        $user = User::factory()->create();
        $this->createStarterBadge();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '50000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 50000
                    && $payout->currency === 'NGN';
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );
    }

    /**
     * Verify that the listener generates a reference containing the user and badge identifiers.
     */
    public function test_cashback_reference_contains_user_and_badge(): void
    {
        $user = User::factory()->create();
        $badge = $this->createStarterBadge();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user, $badge): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 30000
                    && $payout->reference === "cashback-{$user->id}-{$badge->id}";
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );
    }

    /**
     * Verify that a missing cashback setting safely falls back to zero.
     */
    public function test_missing_cashback_setting_defaults_to_zero(): void
    {
        $user = User::factory()->create();
        $this->createStarterBadge();

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 0
                    && $payout->currency === 'NGN';
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $listener->handle(
            new BadgeUnlocked(
                'Starter',
                $user
            )
        );
    }
}
