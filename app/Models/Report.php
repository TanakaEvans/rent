<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A marketplace report raised against a property or user (Module 19).
 * Flows through an admin moderation queue keyed by `status`.
 */
class Report extends Model
{
    use HasFactory;

    public const STATUSES = [
        'open' => 'Open',
        'under_review' => 'Under review',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
        'escalated' => 'Escalated',
    ];

    public const TRANSITIONS = [
        'open' => ['under_review', 'dismissed', 'resolved'],
        'under_review' => ['resolved', 'escalated', 'dismissed'],
        'escalated' => ['under_review', 'resolved'],
        'resolved' => [],
        'dismissed' => ['open'],
    ];

    protected $fillable = [
        'reporter_id',
        'subject_type',
        'subject_id',
        'category',
        'description',
        'status',
        'priority',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'subject_id')->where('subject_type', 'property');
    }
}