<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_no',
        'property_id',
        'tenant_id',
        'category',
        'priority',
        'title',
        'description',
        'status',
        'sla_due_at',
        'escalated_at',
        'approved_quote',
        'assigned_contractor_id',
        'tenant_confirmed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'tenant_confirmed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'approved_quote' => 'decimal:2',
        ];
    }

    /**
     * The maintenance queue state machine (Module 10, FR-03).
     */
    public const STATUSES = [
        'reported' => 'Reported',
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'closed' => 'Closed',
        'declined' => 'Declined',
    ];

    public const TRANSITIONS = [
        'reported' => ['assigned', 'declined'],
        'assigned' => ['in_progress', 'declined'],
        'in_progress' => ['completed'],
        'completed' => ['closed'],
        'closed' => [],
        'declined' => [],
    ];

    public const TERMINAL = ['closed', 'declined'];

    public const PRIORITIES = ['low', 'medium', 'high', 'emergency'];

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The property being repaired.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The tenant who reported the issue.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * The immutable timeline appended to this request (newest last).
     */
    public function actions()
    {
        return $this->hasMany(MaintenanceAction::class, 'request_id')->oldest();
    }

    /**
     * The contractor the owner committed to (assigned state onwards).
     */
    public function contractor()
    {
        return $this->belongsTo(Contractor::class, 'assigned_contractor_id');
    }

    /**
     * The owner's score for the contractor on this job (set once it closes).
     */
    public function rating()
    {
        return $this->hasOne(ContractorRating::class, 'request_id');
    }
}