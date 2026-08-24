<?php

namespace App\Services;

use App\Contracts\PaymentProvider;

class FakePaymentProvider implements PaymentProvider
{
    /**
     * Store all simulated payments made during the current application run.
     */
    public array $payments = [];

    /**
     * Record a simulated cashback payment without calling a real payment provider.
     */
    public function sendCashback(
        int $userId,
        int $amount,
        string $reference
    ): bool {
        $this->payments[] = [
            'user_id' => $userId,
            'amount' => $amount,
            'reference' => $reference,
        ];

        return true;
    }
}