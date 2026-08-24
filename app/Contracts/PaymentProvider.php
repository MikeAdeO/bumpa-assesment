<?php

namespace App\Contracts;

interface PaymentProvider
{
    /**
     * Sends a cashback payment to the user's account.
     */
    public function sendCashback(
        int $userId,
        int $amount,
        string $reference
    ): bool;
}
