<?php

namespace App\Payments\Data;

final readonly class CashbackPayout
{
    public function __construct(
        public int $userId,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $accountNumber,
        public string $bankCode,
        public ?string $accountName = null,
    ) {}
}
