<?php

namespace App\Listeners;

use App\Events\BadgeUnlocked;
use App\Models\Badge;
use App\Models\CashbackPayment;
use App\Payments\Data\CashbackPayout;
use App\Services\PaymentService;
use App\Services\SettingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class ProcessCashback implements ShouldQueue
{
    /**
     * Retry a transient payment provider failure a few times before giving up.
     */
    public int $tries = 3;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SettingService $settingService
    ) {}

    /**
     * Create and process a cashback payment for a newly unlocked badge.
     */
    public function handle(BadgeUnlocked $event): void
    {
        $badge = Badge::where('name', $event->badgeName)->firstOrFail();

        $cashbackAmount = $this->settingService->getInt(
            'badge_cashback_amount'
        );

        $reference = sprintf(
            'cashback-%d-%d',
            $event->user->id,
            $badge->id
        );

        DB::transaction(function () use (
            $event,
            $badge,
            $cashbackAmount,
            $reference
        ): void {
          
            CashbackPayment::createOrFirst(
                [
                    'user_id' => $event->user->id,
                    'badge_id' => $badge->id,
                ],
                [
                    'amount' => $cashbackAmount,
                    'reference' => $reference,
                    'status' => 'pending',
                ]
            );

            $payment = CashbackPayment::query()
                ->where('user_id', $event->user->id)
                ->where('badge_id', $badge->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'processed') {
                return;
            }

            $paymentAccount = $event->user
                ->paymentAccounts()
                ->where('is_active', true)
                ->first();

            $payout = new CashbackPayout(
                userId: $event->user->id,
                amount: $payment->amount,
                currency: 'NGN',
                reference: $payment->reference,
                accountNumber: $paymentAccount?->account_number ?? '',
                bankCode: $paymentAccount?->metadata['bank_code'] ?? '',
                accountName: $paymentAccount?->account_name,
            );

            $successful = $this->paymentService->sendCashback($payout);

            if ($successful) {
                $payment->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                return;
            }

            $payment->update([
                'status' => 'failed',
            ]);
        });
    }
}
