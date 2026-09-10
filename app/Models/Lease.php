<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lease extends Model
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
        'application_id',
        'renewed_from_id',
        'lease_no',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'payment_terms',
        'status',
        'clause_version',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'payment_terms' => 'array',
            'clause_version' => 'integer',
        ];
    }

    /**
     * The lease status labels used across the UI.
     */
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Pending signatures',
        'signed' => 'Signed',
        'active' => 'Active',
        'renewed' => 'Renewed',
        'terminated' => 'Terminated',
    ];

    /**
     * Allowed status transitions (explicit state machine).
     */
    public const TRANSITIONS = [
        'draft' => ['sent', 'terminated'],
        'sent' => ['signed', 'terminated'],
        'signed' => ['active', 'terminated'],
        'active' => ['renewed', 'terminated'],
        'renewed' => ['terminated'],
        'terminated' => [],
    ];

    /**
     * Statuses that close a lease for further state changes.
     */
    public const TERMINAL = ['terminated'];

    /**
     * Determine whether the lease can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The property being leased.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The tenant on the lease.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * The approved application this lease was generated from.
     */
    public function application()
    {
        return $this->belongsTo(RentalApplication::class);
    }

    /**
     * The original lease this renewal continues (FR-05).
     */
    public function renewedFrom()
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /**
     * The renewal leases created from this lease.
     */
    public function renewals()
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    /**
     * The audit trail for this lease (newest first).
     */
    public function history()
    {
        return $this->hasMany(LeaseHistory::class)->latest();
    }

    /**
     * The digital signatures recorded against this lease.
     */
    public function signatures()
    {
        return $this->hasMany(LeaseSignature::class);
    }

    /**
     * The stored agreement document for this lease (M20-lite). A lease keeps a
     * single row whose version bumps on re-store.
     */
    public function document()
    {
        return $this->hasOne(Document::class);
    }

    /**
     * The generated rent schedule for this lease (Wave 4, M9).
     */
    public function rentSchedule()
    {
        return $this->hasOne(RentSchedule::class);
    }

    /**
     * The rent invoices raised against this lease (Wave 4, M9).
     */
    public function rentInvoices()
    {
        return $this->hasMany(RentInvoice::class);
    }

    /**
     * Append an audit entry to the lease trail.
     */
    public function recordHistory(string $action, ?User $performer, ?array $details = []): void
    {
        $this->history()->create([
            'action' => $action,
            'performed_by' => $performer?->id,
            'details' => $details ?: null,
        ]);
    }
}