<?php

namespace App\Services;

use App\Events\PurchaseCompleted;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseService
{
    /**
     * Create a completed purchase for a user and trigger the achievement flow.
     *
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function createCompletedPurchase(
        User $user,
        array $items
    ): Purchase {
        if ($items === []) {
            throw new InvalidArgumentException(
                'A purchase must contain at least one item.'
            );
        }

        $purchase = DB::transaction(function () use ($user, $items): Purchase {
            $products = Product::query()
                ->whereIn(
                    'id',
                    array_column($items, 'product_id')
                )
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $purchaseItems = [];
            $totalAmount = 0;
            $currencyId = null;

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if ($product === null) {
                    throw new InvalidArgumentException(
                        "Product {$item['product_id']} is not available."
                    );
                }

                if ($item['quantity'] < 1) {
                    throw new InvalidArgumentException(
                        'Purchase quantity must be at least one.'
                    );
                }

                if ($currencyId === null) {
                    $currencyId = $product->currency_id;
                }

                if ($currencyId !== $product->currency_id) {
                    throw new InvalidArgumentException(
                        'All products in a purchase must use the same currency.'
                    );
                }

                $itemTotal = $product->price * $item['quantity'];

                $purchaseItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $itemTotal,
                ];

                $totalAmount += $itemTotal;
            }

            $purchase = Purchase::create([
                'user_id' => $user->id,
                'reference' => 'PUR-' . Str::upper(Str::random(16)),
                'status' => PurchaseStatus::Completed,
                'currency_id' => $currencyId,
                'total_amount' => $totalAmount,
                'purchased_at' => now(),
            ]);

            $purchase->items()->createMany($purchaseItems);

            return $purchase;
        });

        PurchaseCompleted::dispatch(
            $purchase->load('user')
        );

        return $purchase->load('items.product', 'currency');
    }
}