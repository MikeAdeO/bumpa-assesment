<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use App\Payments\Data\CashbackPayout;
use Illuminate\Support\Facades\Log;

class LocalPaymentProvider implements PaymentProvider
{
    /**
     * Simulate sending a cashback payment.
     */
    public function sendCashback(CashbackPayout $payout): bool
    {
        Log::info('Cashback payment sent', [
            'user_id' => $payout->userId,
            'amount' => $payout->amount,
            'currency' => $payout->currency,
            'reference' => $payout->reference,
            'account_number' => $payout->accountNumber,
            'bank_code' => $payout->bankCode,
            'account_name' => $payout->accountName,
        ]);

        return true;
    }
}
