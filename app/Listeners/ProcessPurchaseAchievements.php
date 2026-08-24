<?php

namespace App\Listeners;

use App\Events\PurchaseCompleted;
use App\Services\AchievementService;

class ProcessPurchaseAchievements
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function handle(PurchaseCompleted $event): void
    {
        $this->achievementService->process(
            $event->purchase->user
        );
    }
}