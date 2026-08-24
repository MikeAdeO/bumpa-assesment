<?php

namespace App\Models;

use App\Enums\AchievementRuleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'achievement_group_id',
        'name',
        'description',
        'rule_type',
        'threshold',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => AchievementRuleType::class,
            'threshold' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            AchievementGroup::class,
            'achievement_group_id'
        );
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
