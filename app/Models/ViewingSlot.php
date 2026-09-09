<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewingSlot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'owner_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    /**
     * The slot statuses used across the UI.
     */
    public const STATUSES = [
        'available' => 'Available',
        'taken' => 'Taken',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The property the viewing slot belongs to.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The owner who scheduled the slot.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Scope to slots that have not yet finished.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('ends_at', '>', now());
    }
}