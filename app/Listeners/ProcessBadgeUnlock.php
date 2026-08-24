<?php

namespace App\Listeners;

use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Models\Badge;
use App\Models\UserBadge;

class ProcessBadgeUnlock
{
    /**
     * Process newly unlocked achievements and unlock any badges the user has earned.
     */
    public function handle(AchievementUnlocked $event): void
    {
        $user = $event->user;

        $unlockedAchievements = $user->achievements()->count();

        $badges = Badge::query()
            ->where('is_active', true)
            ->where('required_achievements', '<=', $unlockedAchievements)
            ->orderBy('sort_order')
            ->get();

        foreach ($badges as $badge) {
            // Don't unlock the same badge twice.
            $alreadyUnlocked = UserBadge::query()
                ->where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->exists();

            if ($alreadyUnlocked) {
                continue;
            }

            UserBadge::create([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'unlocked_at' => now(),
            ]);

            BadgeUnlocked::dispatch(
                $badge->name,
                $user
            );
        }
    }
}
