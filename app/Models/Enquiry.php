<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
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
        'message',
        'phone',
        'reply',
        'replied_at',
        'status',
        'read_at',
    ];

    /**
     * The enquiry statuses used across the UI.
     */
    public const STATUSES = [
        'new' => 'New',
        'read' => 'Read',
        'replied' => 'Replied',
        'closed' => 'Closed',
    ];

    /**
     * Allowed status transitions (explicit state machine).
     */
    public const TRANSITIONS = [
        'new' => ['read', 'replied', 'closed'],
        'read' => ['replied', 'closed'],
        'replied' => ['closed'],
        'closed' => [],
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the enquiry can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The property the enquiry is about.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The tenant who sent the enquiry.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}