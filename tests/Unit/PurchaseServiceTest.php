<?php

namespace Tests\Unit;

use App\Enums\PurchaseStatus;
use App\Events\PurchaseCompleted;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create the currency used by the purchase test products.
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
     * Create an active product that can be purchased.
     */
    private function createProduct(
        Currency $currency,
        int $price = 10000
    ): Product {
        return Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-'.uniqid(),
            'description' => 'Test product',
            'price' => $price,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
    }

    /**
     * Verify that the service creates a completed purchase with the correct total.
     */
    public function test_creates_completed_purchase(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $currency = $this->createCurrency();
        $product = $this->createProduct($currency, 10000);

        $purchase = app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'user_id' => $user->id,
            'status' => PurchaseStatus::Completed->value,
            'currency_id' => $currency->id,
            'total_amount' => 20000,
        ]);

        $this->assertNotEmpty($purchase->reference);

        Event::assertDispatched(
            PurchaseCompleted::class,
            fn (PurchaseCompleted $event): bool => $event->purchase->id === $purchase->id
        );
    }

    /**
     * Verify that each purchase item stores the product price and calculated total.
     */
    public function test_creates_purchase_items_with_correct_prices(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $currency = $this->createCurrency();

        $product = $this->createProduct(
            $currency,
            15000
        );

        $purchase = app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 15000,
            'total_price' => 45000,
        ]);
    }

    /**
     * Verify that multiple products are included in one purchase and their totals are combined.
     */
    public function test_creates_multiple_purchase_items(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $currency = $this->createCurrency();

        $firstProduct = $this->createProduct($currency, 10000);
        $secondProduct = Product::create([
            'name' => 'Second Product',
            'sku' => 'TEST-'.uniqid(),
            'description' => 'Second test product',
            'price' => 25000,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);

        $purchase = app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $firstProduct->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $secondProduct->id,
                    'quantity' => 1,
                ],
            ]);

        $this->assertSame(45000, $purchase->total_amount);

        $this->assertDatabaseCount('purchase_items', 2);
    }

    /**
     * Verify that an empty purchase is rejected before anything is written to the database.
     */
    public function test_rejects_empty_purchase(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseService::class)
            ->createCompletedPurchase($user, []);
    }

    /**
     * Verify that inactive products cannot be purchased.
     */
    public function test_rejects_inactive_product(): void
    {
        $user = User::factory()->create();
        $currency = $this->createCurrency();

        $product = $this->createProduct($currency);

        $product->update([
            'is_active' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ]);
    }

    /**
     * Verify that a product that does not exist cannot be purchased.
     */
    public function test_rejects_missing_product(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => 999999,
                    'quantity' => 1,
                ],
            ]);
    }

    /**
     * Verify that zero or negative quantities are rejected.
     */
    public function test_rejects_invalid_quantity(): void
    {
        $user = User::factory()->create();
        $currency = $this->createCurrency();
        $product = $this->createProduct($currency);

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $product->id,
                    'quantity' => 0,
                ],
            ]);
    }

    /**
     * Verify that products using different currencies cannot be combined in one purchase.
     */
    public function test_rejects_mixed_currencies(): void
    {
        $user = User::factory()->create();

        $ngn = $this->createCurrency();

        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'minor_unit' => 2,
            'is_active' => true,
        ]);

        $firstProduct = $this->createProduct($ngn);
        $secondProduct = $this->createProduct($usd);

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseService::class)
            ->createCompletedPurchase($user, [
                [
                    'product_id' => $firstProduct->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $secondProduct->id,
                    'quantity' => 1,
                ],
            ]);
    }
}
