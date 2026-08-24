<?php

namespace Tests\Feature;

use App\Enums\AchievementRuleType;
use App\Models\Achievement;
use App\Models\AchievementGroup;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create the achievement groups, achievements, and badges required by the endpoint tests.
     */
    private function seedAchievementProgressData(): void
    {
        $purchasesGroup = AchievementGroup::create([
            'name' => 'Purchases',
            'description' => 'Purchase-based achievements.',
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $purchasesGroup->id,
            'name' => 'First Purchase',
            'description' => 'Make your first purchase.',
            'rule_type' => AchievementRuleType::FirstPurchase,
            'threshold' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $purchasesGroup->id,
            'name' => '5 Purchases',
            'description' => 'Complete five purchases.',
            'rule_type' => AchievementRuleType::PurchaseCount,
            'threshold' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $purchasesGroup->id,
            'name' => '10 Purchases',
            'description' => 'Complete ten purchases.',
            'rule_type' => AchievementRuleType::PurchaseCount,
            'threshold' => 10,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $purchasesGroup->id,
            'name' => '20 Purchases',
            'description' => 'Complete twenty purchases.',
            'rule_type' => AchievementRuleType::PurchaseCount,
            'threshold' => 20,
            'sort_order' => 4,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Starter',
            'description' => 'Unlock two achievements.',
            'required_achievements' => 2,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Advanced',
            'description' => 'Unlock three achievements.',
            'required_achievements' => 3,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Badge::create([
            'name' => 'Expert',
            'description' => 'Unlock four achievements.',
            'required_achievements' => 4,
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    /**
     * Verify that a new user sees the first available achievement and first badge.
     */
    public function test_new_user_sees_first_available_achievement_and_badge(): void
    {
        $this->seedAchievementProgressData();

        $user = User::factory()->create();

        $response = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $response
            ->assertOk()
            ->assertJson([
                'unlocked_achievements' => [],
                'next_available_achievements' => [
                    'First Purchase',
                ],
                'current_badge' => null,
                'next_badge' => 'Starter',
                'remaining_to_unlock_next_badge' => 2,
            ]);
    }

    /**
     * Verify that unlocked achievements are returned by name.
     */
    public function test_unlocked_achievements_are_returned(): void
    {
        $this->seedAchievementProgressData();

        $user = User::factory()->create();

        $firstPurchase = Achievement::where(
            'name',
            'First Purchase'
        )->firstOrFail();

        $fivePurchases = Achievement::where(
            'name',
            '5 Purchases'
        )->firstOrFail();

        UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $firstPurchase->id,
            'unlocked_at' => now(),
        ]);

        UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $fivePurchases->id,
            'unlocked_at' => now(),
        ]);

        $response = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $response
            ->assertOk()
            ->assertJson([
                'unlocked_achievements' => [
                    'First Purchase',
                    '5 Purchases',
                ],
                'next_available_achievements' => [
                    '10 Purchases',
                ],
                'current_badge' => 'Starter',
                'next_badge' => 'Advanced',
                'remaining_to_unlock_next_badge' => 1,
            ]);
    }

    /**
     * Verify that only the next achievement from each achievement group is returned.
     */
    public function test_only_next_achievement_from_each_group_is_returned(): void
    {
        $this->seedAchievementProgressData();

        $referralsGroup = AchievementGroup::create([
            'name' => 'Referrals',
            'description' => 'Referral-based achievements.',
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $referralsGroup->id,
            'name' => 'First Referral',
            'description' => 'Refer your first customer.',
            'rule_type' => AchievementRuleType::PurchaseCount,
            'threshold' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Achievement::create([
            'achievement_group_id' => $referralsGroup->id,
            'name' => '5 Referrals',
            'description' => 'Refer five customers.',
            'rule_type' => AchievementRuleType::PurchaseCount,
            'threshold' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $firstPurchase = Achievement::where(
            'name',
            'First Purchase'
        )->firstOrFail();

        UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $firstPurchase->id,
            'unlocked_at' => now(),
        ]);

        $response = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $response
            ->assertOk()
            ->assertJson([
                'next_available_achievements' => [
                    '5 Purchases',
                    'First Referral',
                ],
            ]);
    }

    /**
     * Verify that the current badge is the highest badge already earned.
     */
    public function test_current_badge_is_highest_unlocked_badge(): void
    {
        $this->seedAchievementProgressData();

        $user = User::factory()->create();

        Achievement::query()
            ->orderBy('sort_order')
            ->limit(3)
            ->get()
            ->each(function (Achievement $achievement) use ($user): void {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);
            });

        $response = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $response
            ->assertOk()
            ->assertJson([
                'current_badge' => 'Advanced',
                'next_badge' => 'Expert',
                'remaining_to_unlock_next_badge' => 1,
            ]);
    }

    /**
     * Verify that a user who has earned every badge has no next badge.
     */
    public function test_fully_completed_user_has_no_next_badge(): void
    {
        $this->seedAchievementProgressData();

        $user = User::factory()->create();

        Achievement::all()->each(
            function (Achievement $achievement) use ($user): void {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);
            }
        );

        $response = $this->getJson(
            "/users/{$user->id}/achievements"
        );

        $response
            ->assertOk()
            ->assertJson([
                'next_available_achievements' => [],
                'current_badge' => 'Expert',
                'next_badge' => null,
                'remaining_to_unlock_next_badge' => 0,
            ]);
    }

    /**
     * Verify that requesting achievements for a nonexistent user returns a 404 response.
     */
    public function test_nonexistent_user_returns_not_found(): void
    {
        $this->seedAchievementProgressData();

        $response = $this->getJson(
            '/users/999999/achievements'
        );

        $response->assertNotFound();
    }
}
