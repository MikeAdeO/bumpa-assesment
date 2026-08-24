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
            // createOrFirst() safely handles two concurrent first-time
            // unlocks racing to insert the same (user_id, badge_id) row: if
            // both miss the initial SELECT, the loser's INSERT hits the
            // unique constraint and it falls back to re-selecting the
            // winner's row instead of throwing.
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

            // Re-select with a row lock held for the rest of the
            // transaction so two workers processing the same (retried or
            // duplicated) BadgeUnlocked event cannot both observe
            // status = "pending" and both send the payout.
            $payment = CashbackPayment::query()
                ->where('user_id', $event->user->id)
                ->where('badge_id', $badge->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'processed') {
                return;
            }

            $payout = new CashbackPayout(
                userId: $event->user->id,
                amount: $payment->amount,
                currency: 'NGN',
                reference: $payment->reference,
                accountNumber: $event->user->bank_account_number,
                bankCode: $event->user->bank_code,
                accountName: $event->user->bank_account_name,
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
