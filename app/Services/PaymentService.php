<?php

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Payments\Data\CashbackPayout;

class PaymentService
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider
    ) {}

    public function sendCashback(CashbackPayout $payout): bool
    {
        return $this->paymentProvider->sendCashback($payout);
    }
}
