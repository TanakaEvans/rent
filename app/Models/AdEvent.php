<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'placement_id',
        'event_type',
    ];

    public function placement()
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }
}