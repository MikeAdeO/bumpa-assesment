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

class CashbackFailureTest extends TestCase
{
    use RefreshDatabase;

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

    private function createCashbackSetting(): void
    {
        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);
    }

    /**
     * Verify that a payment provider failure marks the payment as failed
     * rather than silently leaving it "pending" or crashing the listener.
     */
    public function test_provider_failure_marks_payment_as_failed(): void
    {
        $user = User::factory()->create();
        $badge = $this->createStarterBadge();
        $this->createCashbackSetting();

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->andReturn(false);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $listener->handle(
            new BadgeUnlocked($badge->name, $user)
        );

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'failed',
        ]);
    }

    /**
     * Verify that a badge unlock which previously failed to pay out can be
     * reprocessed (e.g. via a retried queue job) and succeed once the
     * payment provider is healthy again, without creating a duplicate
     * cashback_payments row.
     */
    public function test_failed_payment_can_be_retried_and_succeed(): void
    {
        $user = User::factory()->create();
        $badge = $this->createStarterBadge();
        $this->createCashbackSetting();

        $failingProvider = Mockery::mock(PaymentService::class);
        $failingProvider
            ->shouldReceive('sendCashback')
            ->once()
            ->andReturn(false);

        (new ProcessCashback($failingProvider, new SettingService))
            ->handle(new BadgeUnlocked($badge->name, $user));

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'failed',
        ]);

        $succeedingProvider = Mockery::mock(PaymentService::class);
        $succeedingProvider
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(fn (CashbackPayout $payout): bool => $payout->amount === 30000)
            ->andReturn(true);

        (new ProcessCashback($succeedingProvider, new SettingService))
            ->handle(new BadgeUnlocked($badge->name, $user));

        $this->assertDatabaseCount('cashback_payments', 1);

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'processed',
        ]);
    }
}
