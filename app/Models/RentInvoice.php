<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentInvoice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'tenant_id',
        'lease_id',
        'schedule_id',
        'period_start',
        'period_end',
        'amount',
        'late_fee',
        'status',
        'invoice_no',
        'reminded_at',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * The rent invoice status labels used across the UI.
     */
    public const STATUSES = [
        'draft' => 'Draft',
        'due' => 'Due',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    /**
     * Allowed status transitions (explicit state machine).
     */
    public const TRANSITIONS = [
        'draft' => ['due', 'paid', 'cancelled'],
        'due' => ['paid', 'overdue', 'cancelled'],
        'overdue' => ['paid', 'refunded', 'cancelled'],
        'paid' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * Determine whether the invoice can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The property the billing period covers.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The tenant who owes the rent.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * The lease this invoice belongs to.
     */
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * The schedule this invoice was generated from.
     */
    public function schedule()
    {
        return $this->belongsTo(RentSchedule::class, 'schedule_id');
    }

    /**
     * The latest payment against this invoice (settled pays it).
     */
    public function payment()
    {
        return $this->hasOne(Payment::class, 'invoice_id')->latest('id');
    }

    /**
     * The payments raised against this invoice.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}