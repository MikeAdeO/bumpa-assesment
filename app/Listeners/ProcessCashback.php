<?php

namespace App\Listeners;

use App\Events\BadgeUnlocked;
use App\Models\Badge;
use App\Models\CashbackPayment;
use App\Services\PaymentService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;

class ProcessCashback
{
    /**
     * Create the cashback listener with the services required to process payments.
     */
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SettingService $settingService
    ) {
        //
    }

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
            $payment = CashbackPayment::firstOrCreate(
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

            if ($payment->status === 'processed') {
                return;
            }

            $successful = $this->paymentService->sendCashback(
                userId: $event->user->id,
                amount: $payment->amount,
                reference: $payment->reference,
            );

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