<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use App\Models\User;
use App\Payments\Data\CashbackPayout;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class PaystackPaymentProvider implements PaymentProvider
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public function sendCashback(CashbackPayout $payout): bool
    {
        if (! $payout->accountNumber || ! $payout->bankCode) {
            Log::warning('Cashback payout skipped: no bank account on file for user', [
                'user_id' => $payout->userId,
                'reference' => $payout->reference,
            ]);

            return false;
        }

        $recipientCode = $this->resolveRecipientCode($payout);

        if (! $recipientCode) {
            return false;
        }

        $response = $this->client()->post('/transfer', [
            'source' => 'balance',
            'amount' => $payout->amount,
            'recipient' => $recipientCode,
            'reason' => 'Badge unlock cashback reward',
            'reference' => $payout->reference,
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack transfer request failed', [
                'user_id' => $payout->userId,
                'reference' => $payout->reference,
                'status_code' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        Log::info('Paystack transfer initiated', [
            'user_id' => $payout->userId,
            'reference' => $payout->reference,
            'transfer_code' => $response->json('data.transfer_code'),
        ]);

        return true;
    }

    /**
     * Returns the user's cached Paystack recipient_code, registering one
     * with Paystack first if this is their first payout.
     */
    private function resolveRecipientCode(CashbackPayout $payout): ?string
    {
        $user = User::find($payout->userId);

        if ($user?->paystack_recipient_code) {
            return $user->paystack_recipient_code;
        }

        $response = $this->client()->post('/transferrecipient', [
            'type' => 'nuban',
            'name' => $payout->accountName ?? $user?->name ?? 'Bumpa Customer',
            'account_number' => $payout->accountNumber,
            'bank_code' => $payout->bankCode,
            'currency' => $payout->currency,
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack recipient creation failed', [
                'user_id' => $payout->userId,
                'reference' => $payout->reference,
                'status_code' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $recipientCode = $response->json('data.recipient_code');

        // Not fillable-mass-assignment-sensitive: this is the one field on
        // User that only this class ever writes.
        $user?->update(['paystack_recipient_code' => $recipientCode]);

        return $recipientCode;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson();
    }
}
