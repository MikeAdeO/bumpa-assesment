<?php

namespace Tests\Unit;

use App\Events\BadgeUnlocked;
use App\Listeners\ProcessCashback;
use App\Models\Badge;
use App\Models\CashbackPayment;
use App\Models\Setting;
use App\Models\User;
use App\Payments\Data\CashbackPayout;
use App\Services\PaymentService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CashbackIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create the badge used by the cashback tests.
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
     * Create the cashback setting used by the tests.
     */
    private function createCashbackSetting(): void
    {
        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);
    }

    /**
     * Verify that processing the same badge unlock twice
     * only creates one cashback payment and sends cashback once.
     */
    public function test_same_badge_does_not_create_duplicate_cashback(): void
    {
        $user = User::factory()->create();
        $badge = $this->createStarterBadge();

        $this->createCashbackSetting();

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user, $badge): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 30000
                    && $payout->currency === 'NGN'
                    && $payout->reference === "cashback-{$user->id}-{$badge->id}";
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        $event = new BadgeUnlocked(
            $badge->name,
            $user
        );

        // First processing should create and process the cashback.
        $listener->handle($event);

        // Second processing should detect the existing processed payment
        // and must not send cashback again.
        $listener->handle($event);

        $this->assertDatabaseCount('cashback_payments', 1);

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'amount' => 30000,
            'status' => 'processed',
        ]);
    }

    /**
     * Verify that a successfully processed cashback
     * is not sent again when the event is retried.
     */
    public function test_processed_cashback_is_not_sent_again(): void
    {
        $user = User::factory()->create();
        $badge = $this->createStarterBadge();

        $this->createCashbackSetting();

        $paymentService = Mockery::mock(PaymentService::class);

        $paymentService
            ->shouldReceive('sendCashback')
            ->once()
            ->withArgs(function (CashbackPayout $payout) use ($user, $badge): bool {
                return $payout->userId === $user->id
                    && $payout->amount === 30000
                    && $payout->currency === 'NGN'
                    && $payout->reference === "cashback-{$user->id}-{$badge->id}";
            })
            ->andReturn(true);

        $listener = new ProcessCashback(
            $paymentService,
            new SettingService
        );

        // First event processes the cashback.
        $listener->handle(
            new BadgeUnlocked($badge->name, $user)
        );

        // Retried event must not send cashback again.
        $listener->handle(
            new BadgeUnlocked($badge->name, $user)
        );

        $this->assertSame(
            1,
            CashbackPayment::where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->count()
        );

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'amount' => 30000,
            'status' => 'processed',
        ]);
    }
}
