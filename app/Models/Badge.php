<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'required_achievements',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_achievements' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}