<?php

namespace Tests\Unit;

use App\Enums\PurchaseStatus;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBadge;
use App\Models\CashbackPayment;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a completed purchase unlocks the first purchase achievement.
     */
    public function test_completed_purchase_unlocks_first_purchase_achievement(): void
    {
        $user = User::factory()->create();

        $currency = $this->createCurrency();
        $product = $this->createProduct($currency);

        $this->createAchievements();
        $this->createBadges();

        app(PurchaseService::class)->createCompletedPurchase(
            $user,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ]
        );

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => Achievement::where(
                'name',
                'First Purchase'
            )->value('id'),
        ]);
    }

    /**
     * Verify that enough completed purchases unlock the Starter badge.
     */
    public function test_completed_purchases_unlock_starter_badge(): void
    {
        $user = User::factory()->create();

        $currency = $this->createCurrency();
        $product = $this->createProduct($currency);

        $this->createAchievements();
        $this->createBadges();

        $purchaseService = app(PurchaseService::class);

        for ($i = 0; $i < 5; $i++) {
            $purchaseService->createCompletedPurchase(
                $user,
                [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ]
            );
        }

        $starterBadge = Badge::where('name', 'Starter')->firstOrFail();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $starterBadge->id,
        ]);
    }

    /**
     * Verify that unlocking a badge creates the configured cashback payment.
     */
    public function test_completed_purchases_create_cashback_payment(): void
    {
        $user = User::factory()->create();

        $currency = $this->createCurrency();
        $product = $this->createProduct($currency);

        $this->createAchievements();
        $this->createBadges();

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);

        $purchaseService = app(PurchaseService::class);

        for ($i = 0; $i < 5; $i++) {
            $purchaseService->createCompletedPurchase(
                $user,
                [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ]
            );
        }

        $starterBadge = Badge::where('name', 'Starter')->firstOrFail();

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'badge_id' => $starterBadge->id,
            'amount' => 30000,
        ]);
    }

    /**
     * Create the currency required by the purchase flow tests.
     */
    private function createCurrency(): Currency
    {
        return Currency::create([
            'code' => 'NGN',
            'name' => 'Nigerian Naira',
            'symbol' => '₦',
            'minor_unit' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Create an active product for the purchase flow tests.
     */
    private function createProduct(Currency $currency): Product
    {
        return Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-' . uniqid(),
            'description' => 'Test product',
            'price' => 10000,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
    }

    /**
     * Create the achievements required to exercise the complete purchase flow.
     */
    private function createAchievements(): void
    {
        $group = \App\Models\AchievementGroup::create([
            'name' => 'Purchase Achievements',
        ]);

        Achievement::create([
            'achievement_group_id' => $group->id,
            'name' => 'First Purchase',
            'rule_type' => 'first_purchase',
            'threshold' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $group->id,
            'name' => '5 Purchases',
            'rule_type' => 'purchase_count',
            'threshold' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Create the badges required to exercise badge unlocking in the purchase flow.
     */
    private function createBadges(): void
    {
        Badge::create([
            'name' => 'Starter',
            'required_achievements' => 2,
            'sort_order' => 1,
        ]);
    }
}