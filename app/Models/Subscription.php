<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'grace' => 'Grace',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'cycle',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'cycle' => 'string',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'details' => 'array',
        ];
    }

    /**
     * The owner this subscription belongs to.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The plan currently applied to this subscription.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * All invoices raised against this subscription.
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * All lifecycle events recorded against this subscription.
     */
    public function history()
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function recordEvent(string $event, array $details = []): SubscriptionHistory
    {
        return $this->history()->create([
            'event' => $event,
            'details' => $details,
        ]);
    }

    /**
     * The pending downgrade plan id from a deferred downgrade, if any.
     */
    public function pendingDowngradeTo(): ?int
    {
        return $this->details['pending_downgrade_to'] ?? null;
    }

    /**
     * The pending upgrade plan id from a deferred (apply_at_renewal) upgrade.
     */
    public function pendingUpgradeTo(): ?int
    {
        return $this->details['pending_upgrade_to'] ?? null;
    }

    /**
     * The grace deadline, when the subscription is in grace.
     */
    public function graceUntil(): ?\Illuminate\Support\Carbon
    {
        if ($this->status !== 'grace') {
            return null;
        }

        $until = $this->details['grace_until'] ?? null;

        return $until ? \Illuminate\Support\Carbon::parse($until) : $this->ends_at?->copy();
    }
}