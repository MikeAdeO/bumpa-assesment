<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\AchievementGroup;
use App\Models\Badge;
use App\Models\User;

class AchievementProgressService
{
    /**
     * Build the complete achievement and badge progress response for a user.
     *
     * @return array{
     *     unlocked_achievements: array<int, string>,
     *     next_available_achievements: array<int, string>,
     *     current_badge: string|null,
     *     next_badge: string|null,
     *     remaining_to_unlock_next_badge: int
     * }
     */
    public function getProgress(User $user): array
    {
        $unlockedAchievementIds = $user->achievements()
            ->pluck('achievement_id');

        $unlockedAchievements = Achievement::query()
            ->whereIn('id', $unlockedAchievementIds)
            ->orderBy('sort_order')
            ->pluck('name')
            ->values()
            ->all();

        $nextAvailableAchievements = $this->getNextAvailableAchievements(
            $unlockedAchievementIds
        );

        $badgeProgress = $this->getBadgeProgress(
            $unlockedAchievementIds->count()
        );

        return [
            'unlocked_achievements' => $unlockedAchievements,
            'next_available_achievements' => $nextAvailableAchievements,
            'current_badge' => $badgeProgress['current_badge'],
            'next_badge' => $badgeProgress['next_badge'],
            'remaining_to_unlock_next_badge' => $badgeProgress['remaining'],
        ];
    }

    /**
     * Get the next achievement available from each active achievement group.
     */
    private function getNextAvailableAchievements(
        $unlockedAchievementIds
    ): array {
        return AchievementGroup::query()
            ->where('is_active', true)
            ->with([
                'achievements' => function ($query) use ($unlockedAchievementIds) {
                    $query
                        ->where('is_active', true)
                        ->whereNotIn('id', $unlockedAchievementIds)
                        ->orderBy('sort_order');
                },
            ])
            ->get()
            ->map(
                fn ($group) => $group->achievements->first()?->name
            )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Determine the user's current badge and the next badge they can earn.
     *
     * @return array{
     *     current_badge: string|null,
     *     next_badge: string|null,
     *     remaining: int
     * }
     */
    private function getBadgeProgress(int $unlockedCount): array
    {
        $badges = Badge::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $currentBadge = $badges
            ->filter(
                fn (Badge $badge) => $badge->required_achievements <= $unlockedCount
            )
            ->last();

        $nextBadge = $badges
            ->first(
                fn (Badge $badge) => $badge->required_achievements > $unlockedCount
            );

        return [
            'current_badge' => $currentBadge?->name,
            'next_badge' => $nextBadge?->name,
            'remaining' => $nextBadge
                ? $nextBadge->required_achievements - $unlockedCount
                : 0,
        ];
    }
}
