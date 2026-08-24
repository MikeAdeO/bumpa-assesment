<?php

namespace App\Listeners;

use App\Events\BadgeUnlocked;
use App\Services\PaymentService;
use App\Services\SettingService;

class ProcessCashback
{
    /**
     * Create the cashback listener with the services required to process the payment.
     */
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SettingService $settingService
    ) {
        //
    }

    /**
     * Send the configured cashback amount to the user when a badge is unlocked.
     */
    public function handle(BadgeUnlocked $event): void
    {
        $cashbackAmount = $this->settingService->getInt(
            'badge_cashback_amount'
        );

        $reference = sprintf(
            'cashback-%d-%s',
            $event->user->id,
            strtolower(str_replace(' ', '-', $event->badgeName))
        );

        $this->paymentService->sendCashback(
            userId: $event->user->id,
            amount: $cashbackAmount,
            reference: $reference,
        );
    }
}