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
            // createOrFirst() is both the "don't unlock twice" check and the
            // write, in one race-safe step: if two purchases from the same
            // user get processed concurrently and both reach this line for
            // the same badge, the loser's insert hits the unique constraint
            // on (user_id, badge_id) and it falls back to the winner's row
            // instead of throwing — so only the winner's process dispatches
            // BadgeUnlocked below.
            $userBadge = UserBadge::createOrFirst(
                [
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                ],
                [
                    'unlocked_at' => now(),
                ]
            );

            if (! $userBadge->wasRecentlyCreated) {
                continue;
            }

            BadgeUnlocked::dispatch(
                $badge->name,
                $user
            );
        }
    }
}
