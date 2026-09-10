<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tradesperson on the platform registry (Module 11).
 *
 * Contractors are registered users only when they hold a login (user_id
 * nullable); otherwise the profile stands alone. Status follows FR-02:
 * unverified -> vetting -> verified, with verified <-> suspended moderation.
 * Only `verified` contractors are assignable to maintenance jobs (AC-01/AC-03).
 */
class Contractor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'contact',
        'service_area',
        'status',
        'rating_avg',
        'jobs_completed',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'service_area' => 'array',
            'rating_avg' => 'decimal:2',
            'jobs_completed' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * The registry state machine (Module 11, FR-02).
     */
    public const STATUSES = [
        'unverified' => 'Unverified',
        'vetting' => 'Vetting',
        'verified' => 'Verified',
        'suspended' => 'Suspended',
    ];

    public const TRANSITIONS = [
        'unverified' => ['vetting'],
        'vetting' => ['verified', 'unverified'],
        'verified' => ['suspended', 'vetting', 'unverified'],
        'suspended' => ['verified', 'unverified'],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The optional platform login behind this profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The trades this contractor offers (trade + rate).
     */
    public function trades()
    {
        return $this->hasMany(ContractorTrade::class);
    }

    /**
     * The maintenance requests this contractor has been assigned.
     */
    public function assignments()
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_contractor_id');
    }

    /**
     * The owner scores this contractor earned on closed jobs (FR-04).
     */
    public function ratings()
    {
        return $this->hasMany(ContractorRating::class);
    }
}