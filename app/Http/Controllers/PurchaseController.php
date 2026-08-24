<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    /**
     * Create the purchase controller with the purchase service.
     */
    public function __construct(
        private readonly PurchaseService $purchaseService
    ) {
        //
    }

    /**
     * Record a completed purchase for the user, which in turn drives the
     * achievement -> badge -> cashback event chain (see PurchaseCompleted).
     */
    public function store(
        StorePurchaseRequest $request,
        User $user
    ): JsonResponse {
        try {
            $purchase = $this->purchaseService->createCompletedPurchase(
                $user,
                $request->items()
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'reference' => $purchase->reference,
            'status' => $purchase->status->value,
            'currency' => $purchase->currency->code,
            'total_amount' => $purchase->total_amount,
            'purchased_at' => $purchase->purchased_at,
            'items' => $purchase->items->map(fn ($item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]),
        ], 201);
    }
}
