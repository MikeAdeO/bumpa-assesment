<?php

namespace App\Contracts;

use App\Payments\Data\CashbackPayout;

interface PaymentProvider
{
    public function sendCashback(
        CashbackPayout $payout
    ): bool;
}
