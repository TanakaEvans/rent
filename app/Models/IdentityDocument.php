<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentityDocument extends Model
{
    /**
     * Accepted identity-evidence types (S4). The national/physical ID card and
     * the driving licence are the two documents supported today; the S5
     * review flow reads only these.
     */
    public const KYC_TYPES = ['national_id', 'driving_licence'];

    /**
     * Evidence lifecycle. S4 only ever writes `pending`; S5 adds the
     * Admin/Superuser approve/revoke transitions.
     *
     *   pending  → approved | rejected   (review decision)
     *   approved → rejected              (revoke — a badge is withdrawn)
     *   rejected → (terminal)            — the tenant re-uploads to come back
     */
    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * Explicit review transitions. `rejected` is terminal: a tenant whose
     * evidence was rejected or revoked re-enters the queue by uploading a
     * replacement (unique user×type, which resets the row to `pending`).
     */
    public const TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['rejected'],
        'rejected' => [],
    ];

    /**
     * Whether a reviewer decision may move this document to the given status.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'original_name',
        'mime',
        'size',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * The tenant whose identity evidence this is.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Append-only audit trail of every upload/replace/removal.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(IdentityDocumentAudit::class, 'document_id');
    }
}