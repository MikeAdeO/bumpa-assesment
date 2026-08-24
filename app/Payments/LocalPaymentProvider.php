<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use Illuminate\Support\Facades\Log;

class LocalPaymentProvider implements PaymentProvider
{
    /**
     * Send a cashback payment through the local payment provider.
     *
     * This implementation simulates the provider integration locally.
     * A real provider such as Paystack or Flutterwave can replace this
     * class later without changing the rest of the cashback flow.
     */
    public function sendCashback(
        int $userId,
        int $amount,
        string $reference
    ): bool {
        Log::info('Cashback payment sent', [
            'user_id' => $userId,
            'amount' => $amount,
            'reference' => $reference,
        ]);

        return true;
    }
}