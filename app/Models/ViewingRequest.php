<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewingRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'tenant_id',
        'slot_id',
        'request_message',
        'status',
        'outcome',
    ];

    /**
     * The request lifecycle. Each value lists who is allowed to trigger it.
     *
     * @var array<string, string[]>
     */
    public const TRANSITIONS = [
        'requested' => ['accept', 'decline', 'reschedule', 'cancel'],
        'accepted' => ['complete', 'no-show', 'cancel', 'reschedule'],
        'rescheduled' => ['confirm', 'decline', 'cancel'],
    ];

    public const TERMINAL = ['declined', 'completed', 'cancelled', 'no-show'];

    /**
     * The property the viewing is scheduled for.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The tenant who requested the viewing.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * The slot the viewing is attached to.
     */
    public function slot()
    {
        return $this->belongsTo(ViewingSlot::class);
    }

    /**
     * Scope to bookings that are still active (not yet settled).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', self::TERMINAL);
    }
}