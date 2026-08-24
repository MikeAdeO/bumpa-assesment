<?php

namespace App\Payments\Data;

/**
 * What a payment provider needs to send a cashback payout. The account
 * fields are nullable because a user isn't guaranteed to have payout
 * details on file (see `users.bank_account_number` etc.) — ProcessCashback
 * populates them from the user when present and leaves them null otherwise.
 * LocalPaymentProvider just logs whatever it's given; PaystackPaymentProvider
 * requires them to actually send a transfer and fails gracefully without them.
 */
final readonly class CashbackPayout
{
    public function __construct(
        public int $userId,
        public int $amount,
        public string $currency,
        public string $reference,
        public ?string $accountNumber = null,
        public ?string $bankCode = null,
        public ?string $accountName = null,
    ) {}
}
