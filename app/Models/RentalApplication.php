<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalApplication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'applicant_id',
        'message',
        'status',
        'reject_reason',
        'reviewed_by',
    ];

    /**
     * The application status labels used across the UI.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'shortlisted' => 'Shortlisted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    /**
     * Allowed status transitions (explicit state machine).
     * Rejected applications are terminal; an approved application stays
     * approved until Wave 3 slice 2 surfaces the lease handover.
     */
    public const TRANSITIONS = [
        'pending' => ['shortlisted', 'approved', 'rejected'],
        'shortlisted' => ['pending', 'approved', 'rejected'],
        'approved' => [],
        'rejected' => [],
    ];

    /**
     * Terminal application statuses.
     */
    public const TERMINAL = ['approved', 'rejected'];

    /**
     * Determine whether the application can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Get the property the application references.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the applicant (tenant) who submitted the application.
     */
    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * Get the owner who reviewed (approved/shortlisted/rejected) the application.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}