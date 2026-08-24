<?php

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Payments\Data\CashbackPayout;


class FakePaymentProvider implements PaymentProvider
{
    /**
     * Store all simulated payments made during the current application run.
     *
     * @var array<int, array{user_id: int, amount: int, reference: string}>
     */
    public array $payments = [];

    /**
     * Record a simulated cashback payment without calling a real payment provider.
     */
    public function sendCashback(CashbackPayout $payout): bool
    {
        $this->payments[] = [
            'user_id' => $payout->userId,
            'amount' => $payout->amount,
            'reference' => $payout->reference,
        ];

        return true;
    }
}
