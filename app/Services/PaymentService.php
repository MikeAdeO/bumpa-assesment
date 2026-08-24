<?php

namespace App\Services;

use App\Contracts\PaymentProvider;

class PaymentService
{
    /**
     * Create the payment service with the configured payment provider.
     */
    public function __construct(
        private readonly PaymentProvider $paymentProvider
    ) {
        //
    }

    /**
     * Send the fixed cashback amount to a user after they unlock a badge.
     */
    public function sendCashback(
        int $userId,
        int $amount,
        string $reference
    ): bool {
        return $this->paymentProvider->sendCashback(
            $userId,
            $amount,
            $reference
        );
    }
}