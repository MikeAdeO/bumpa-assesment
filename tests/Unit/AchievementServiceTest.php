<?php

namespace Tests\Unit;

use App\Enums\AchievementRuleType;
use App\Enums\PurchaseStatus;
use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\AchievementGroup;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Product $product;

    private AchievementGroup $purchaseGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::create([
            'code' => 'NGN',
            'name' => 'Nigerian Naira',
            'symbol' => '₦',
            'minor_unit' => 2,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'description' => 'Product used in automated tests.',
            'price' => 10000,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ]);

        $this->purchaseGroup = AchievementGroup::create([
            'name' => 'Purchases',
            'description' => 'Achievements based on purchase activity.',
            'is_active' => true,
        ]);
    }

    public function test_first_purchase_unlocks_first_purchase_achievement(): void
    {
        Event::fake();

        $achievement = $this->createAchievement(
            'First Purchase',
            AchievementRuleType::FirstPurchase,
            1
        );

        $user = User::factory()->create();

        $this->createCompletedPurchase($user);

        app(AchievementService::class)->process($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        Event::assertDispatched(
            AchievementUnlocked::class,
            fn (AchievementUnlocked $event) => $event->achievementName === 'First Purchase'
                && $event->user->is($user)
        );
    }

    public function test_five_purchases_unlock_five_purchases_achievement(): void
    {
        $achievement = $this->createAchievement(
            '5 Purchases',
            AchievementRuleType::PurchaseCount,
            5
        );

        $user = User::factory()->create();

        $this->createPurchases($user, 5);

        app(AchievementService::class)->process($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    public function test_achievement_is_not_unlocked_twice(): void
    {
        Event::fake();

        $achievement = $this->createAchievement(
            'First Purchase',
            AchievementRuleType::FirstPurchase,
            1
        );

        $user = User::factory()->create();

        $this->createCompletedPurchase($user);

        $service = app(AchievementService::class);

        $service->process($user);
        $service->process($user);

        $this->assertDatabaseCount('user_achievements', 1);

        Event::assertDispatchedTimes(
            AchievementUnlocked::class,
            1
        );
    }

    public function test_pending_purchase_does_not_unlock_achievement(): void
    {
        $achievement = $this->createAchievement(
            'First Purchase',
            AchievementRuleType::FirstPurchase,
            1
        );

        $user = User::factory()->create();

        $this->createPurchase(
            $user,
            PurchaseStatus::Pending
        );

        app(AchievementService::class)->process($user);

        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    public function test_multiple_achievements_can_unlock_from_the_same_purchase_count(): void
    {
        $firstPurchase = $this->createAchievement(
            'First Purchase',
            AchievementRuleType::FirstPurchase,
            1
        );

        $threePurchases = $this->createAchievement(
            '3 Purchases',
            AchievementRuleType::PurchaseCount,
            3
        );

        $user = User::factory()->create();

        $this->createPurchases($user, 3);

        app(AchievementService::class)->process($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $firstPurchase->id,
        ]);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $threePurchases->id,
        ]);

        $this->assertDatabaseCount('user_achievements', 2);
    }

    private function createAchievement(
        string $name,
        AchievementRuleType $ruleType,
        int $threshold
    ): Achievement {
        return Achievement::create([
            'achievement_group_id' => $this->purchaseGroup->id,
            'name' => $name,
            'description' => null,
            'rule_type' => $ruleType,
            'threshold' => $threshold,
            'sort_order' => $threshold,
            'is_active' => true,
        ]);
    }

    private function createPurchases(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createCompletedPurchase($user);
        }
    }

    private function createCompletedPurchase(User $user): Purchase
    {
        return $this->createPurchase(
            $user,
            PurchaseStatus::Completed
        );
    }

    private function createPurchase(
        User $user,
        PurchaseStatus $status
    ): Purchase {
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'reference' => fake()->unique()->uuid(),
            'status' => $status,
            'currency_id' => $this->currency->id,
            'total_amount' => $this->product->price,
            'purchased_at' => $status === PurchaseStatus::Completed
                ? now()
                : null,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $this->product->price,
            'total_price' => $this->product->price,
        ]);

        return $purchase;
    }
}
