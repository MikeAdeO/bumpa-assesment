<?php

namespace App\Listeners;

use App\Events\PurchaseCompleted;
use App\Services\AchievementService;

class ProcessPurchaseAchievements
{
    /**
     * Create the listener with the achievement service used to process user progress.
     */
    public function __construct(
        private readonly AchievementService $achievementService
    ) {
        //
    }

    /**
     * Process the user's achievements after a purchase has been completed.
     */
    public function handle(PurchaseCompleted $event): void
    {
        $this->achievementService->process(
            $event->purchase->user
        );
    }
}
