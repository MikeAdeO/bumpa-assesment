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

        // Computed once per call rather than once per achievement checked —
        // it doesn't change while we evaluate the achievements below, so
        // re-querying it inside the loop would just be N redundant COUNT
        // queries for N achievements.
        $purchaseCount = $this->purchaseCount($user);

        foreach ($achievements as $achievement) {
            if ($this->hasUnlocked($user, $achievement)) {
                continue;
            }

            if ($this->qualifies($achievement, $purchaseCount)) {
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
     * Check whether the user's completed purchase count satisfies an achievement's rule.
     */
    private function qualifies(
        Achievement $achievement,
        int $purchaseCount
    ): bool {
        return match ($achievement->rule_type) {
            AchievementRuleType::FirstPurchase => $purchaseCount >= 1,

            AchievementRuleType::PurchaseCount => $purchaseCount >= $achievement->threshold,
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
