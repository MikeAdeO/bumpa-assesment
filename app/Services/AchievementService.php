<?php

namespace App\Services;

use App\Enums\AchievementRuleType;
use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Check the user's progress and unlock every achievement they have earned.
     */
    public function process(User $user): void
    {
        $achievements = Achievement::query()
            ->where('is_active', true)
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        foreach ($achievements as $achievement) {
            if ($this->hasUnlocked($user, $achievement)) {
                continue;
            }

            if ($this->qualifies($user, $achievement)) {
                $this->unlock($user, $achievement);
            }
        }
    }

    /**
     * Check whether the user has already unlocked a specific achievement.
     */
    private function hasUnlocked(
        User $user,
        Achievement $achievement
    ): bool {
        return UserAchievement::query()
            ->where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->exists();
    }

    /**
     * Check whether the user's completed purchases satisfy an achievement's rule.
     */
    private function qualifies(
        User $user,
        Achievement $achievement
    ): bool {
        return match ($achievement->rule_type) {
            AchievementRuleType::FirstPurchase =>
                $this->purchaseCount($user) >= 1,

            AchievementRuleType::PurchaseCount =>
                $this->purchaseCount($user) >= $achievement->threshold,
        };
    }

    /**
     * Count only completed purchases because pending or failed purchases should not earn achievements.
     */
    private function purchaseCount(User $user): int
    {
        return $user->purchases()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Record the achievement unlock and notify the rest of the system.
     */
    private function unlock(
        User $user,
        Achievement $achievement
    ): void {
        DB::transaction(function () use ($user, $achievement): void {
            UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        });

        AchievementUnlocked::dispatch(
            $achievement->name,
            $user
        );
    }
}