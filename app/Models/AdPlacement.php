<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacement extends Model
{
    /**
     * Explicit state machine for a placement window (Module 13).
     * reserved -> active -> expired | cancelled; paused freezes the window.
     */
    public const STATUS = [
        'reserved',
        'active',
        'paused',
        'expired',
        'cancelled',
    ];

    /**
     * Statuses that occupy a property's single placement slot.
     */
    public const OPEN_STATUSES = ['reserved', 'active', 'paused'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'owner_id',
        'package_id',
        'amount',
        'starts_at',
        'ends_at',
        'paused_at',
        'paid_at',
        'status',
        'credit_amount',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function package()
    {
        return $this->belongsTo(AdPackage::class);
    }

    public function events()
    {
        return $this->hasMany(AdEvent::class, 'placement_id');
    }

    /**
     * Whether this placement still occupies the property's slot.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}