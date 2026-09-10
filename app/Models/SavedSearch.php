<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's persisted marketplace search (Module 04 Phase 2 / Marketplace
 * §47). `criteria` mirrors the normalized search filters; `notify` opts the
 * tenant into alerting when new listings match.
 */
class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'criteria',
        'notify',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'notify' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}