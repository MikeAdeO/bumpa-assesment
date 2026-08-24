<?php

namespace Tests\Unit;

use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Listeners\ProcessBadgeUnlock;
use App\Models\Achievement;
use App\Models\AchievementGroup;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BadgeUnlockTest extends TestCase
{
    use RefreshDatabase;

    protected function createBadges(): void
    {
        Badge::create([
            'name' => 'Starter',
            'required_achievements' => 2,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Advanced',
            'required_achievements' => 3,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Expert',
            'required_achievements' => 4,
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    protected function createAchievements(int $count): Collection
    {
        $group = AchievementGroup::create([
            'name' => 'Purchases',
            'description' => 'Purchase achievements',
            'is_active' => true,
        ]);

        return Achievement::factory()
            ->count($count)
            ->create([
                'achievement_group_id' => $group->id,
            ]);
    }

    public function test_two_achievements_unlock_starter_badge(): void
    {
        Event::fake([BadgeUnlocked::class]);

        $this->createBadges();

        $user = User::factory()->create();
        $achievements = $this->createAchievements(2);

        foreach ($achievements as $achievement) {
            UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        }

        app(ProcessBadgeUnlock::class)->handle(
            new AchievementUnlocked(
                $achievements->last()->name,
                $user
            )
        );

        $starterBadge = Badge::where('name', 'Starter')->first();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $starterBadge->id,
        ]);

        Event::assertDispatched(
            BadgeUnlocked::class,
            fn (BadgeUnlocked $event) => $event->badgeName === 'Starter'
                && $event->user->is($user)
        );
    }

    public function test_three_achievements_unlock_advanced_badge(): void
    {
        Event::fake([BadgeUnlocked::class]);

        $this->createBadges();

        $user = User::factory()->create();
        $achievements = $this->createAchievements(3);

        foreach ($achievements as $achievement) {
            UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        }

        app(ProcessBadgeUnlock::class)->handle(
            new AchievementUnlocked(
                $achievements->last()->name,
                $user
            )
        );

        $advancedBadge = Badge::where('name', 'Advanced')->first();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $advancedBadge->id,
        ]);

        Event::assertDispatched(
            BadgeUnlocked::class,
            fn (BadgeUnlocked $event) => $event->badgeName === 'Advanced'
                && $event->user->is($user)
        );
    }

    public function test_four_achievements_unlock_expert_badge(): void
    {
        Event::fake([BadgeUnlocked::class]);

        $this->createBadges();

        $user = User::factory()->create();
        $achievements = $this->createAchievements(4);

        foreach ($achievements as $achievement) {
            UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        }

        app(ProcessBadgeUnlock::class)->handle(
            new AchievementUnlocked(
                $achievements->last()->name,
                $user
            )
        );

        $expertBadge = Badge::where('name', 'Expert')->first();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $expertBadge->id,
        ]);

        Event::assertDispatched(
            BadgeUnlocked::class,
            fn (BadgeUnlocked $event) => $event->badgeName === 'Expert'
                && $event->user->is($user)
        );
    }

    public function test_same_badge_is_not_unlocked_twice(): void
    {
        Event::fake([BadgeUnlocked::class]);

        $this->createBadges();

        $user = User::factory()->create();
        $achievements = $this->createAchievements(2);

        foreach ($achievements as $achievement) {
            UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        }

        $event = new AchievementUnlocked(
            $achievements->last()->name,
            $user
        );

        $listener = app(ProcessBadgeUnlock::class);

        // Processing the same event twice should still create only one badge.
        $listener->handle($event);
        $listener->handle($event);

        $starterBadge = Badge::where('name', 'Starter')->first();

        $this->assertSame(
            1,
            UserBadge::where('user_id', $user->id)
                ->where('badge_id', $starterBadge->id)
                ->count()
        );

        Event::assertDispatchedTimes(BadgeUnlocked::class, 1);
    }
}
