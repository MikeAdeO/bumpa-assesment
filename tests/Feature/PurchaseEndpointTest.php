<?php

namespace Tests\Feature;

use App\Enums\AchievementRuleType;
use App\Models\Achievement;
use App\Models\AchievementGroup;
use App\Models\Badge;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Product $product;

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
            'sku' => 'TEST-PURCHASE-ENDPOINT',
            'description' => 'Product used in purchase endpoint tests.',
            'price' => 10000,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ]);

        $group = AchievementGroup::create([
            'name' => 'Purchases',
            'description' => 'Purchase-based achievements.',
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $group->id,
            'name' => 'First Purchase',
            'rule_type' => AchievementRuleType::FirstPurchase,
            'threshold' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Starter',
            'required_achievements' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Setting::create([
            'key' => 'badge_cashback_amount',
            'value' => '30000',
        ]);
    }

    /**
     * Verify that a valid purchase is recorded and returned.
     */
    public function test_purchase_is_created(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            "/users/{$user->id}/purchases",
            [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJson([
                'status' => 'completed',
                'currency' => 'NGN',
                'total_amount' => 20000,
            ]);

        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'total_amount' => 20000,
        ]);
    }

    /**
     * Verify that a single purchase drives the full
     * achievement -> badge -> cashback chain end to end over HTTP.
     */
    public function test_purchase_unlocks_achievement_badge_and_cashback(): void
    {
        $user = User::factory()->create();

        $this->postJson(
            "/users/{$user->id}/purchases",
            [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]
        )->assertCreated();

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('cashback_payments', [
            'user_id' => $user->id,
            'amount' => 30000,
            'status' => 'processed',
        ]);

        $achievementsResponse = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $achievementsResponse->assertOk()->assertJson([
            'unlocked_achievements' => ['First Purchase'],
            'current_badge' => 'Starter',
        ]);
    }

    /**
     * Verify that an empty item list is rejected with a validation error.
     */
    public function test_empty_items_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            "/users/{$user->id}/purchases",
            ['items' => []]
        );

        $response->assertUnprocessable();
    }

    /**
     * Verify that a nonexistent product is rejected with a validation error.
     */
    public function test_nonexistent_product_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            "/users/{$user->id}/purchases",
            [
                'items' => [
                    ['product_id' => 999999, 'quantity' => 1],
                ],
            ]
        );

        $response->assertUnprocessable();
    }

    /**
     * Verify that an inactive product passes request validation (it exists)
     * but is rejected by the domain rule with a 422 and a clear message.
     */
    public function test_inactive_product_is_rejected_by_domain_rule(): void
    {
        $this->product->update(['is_active' => false]);

        $user = User::factory()->create();

        $response = $this->postJson(
            "/users/{$user->id}/purchases",
            [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('purchases', ['user_id' => $user->id]);
    }
}
