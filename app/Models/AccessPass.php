<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid grant of platform access for one configured period (Module 24 —
 * `access.*`). Created only when charging is on and the free window has closed;
 * a user "has access" while they hold a pass whose `ends_at` is in the future.
 */
class AccessPass extends Model
{
    protected $fillable = [
        'user_id',
        'starts_at',
        'ends_at',
        'amount',
        'currency',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
