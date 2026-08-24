<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbackPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'badge_id',
        'amount',
        'reference',
        'status',
        'processed_at',
    ];

    /**
     * Cast payment values into the appropriate PHP types.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who is receiving the cashback payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the badge that triggered the cashback payment.
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}