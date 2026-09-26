<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityDocumentAudit extends Model
{
    /**
     * The identity-evidence events the trail records. S4 self-service:
     * `uploaded` on first submission, `replaced` when a type's document is
     * re-uploaded, `removed` when the tenant deletes their evidence. S5 review
     * decisions by Admin/Superuser: `approved`, `rejected` (with optional
     * note) and `revoked` (an approved document withdrawn).
     */
    public const ACTIONS = ['uploaded', 'replaced', 'removed', 'approved', 'rejected', 'revoked'];

    protected $fillable = [
        'user_id',
        'document_id',
        'action',
        'actor_id',
        'details',
    ];

    /**
     * The tenant whose identity evidence was touched.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The evidence that was uploaded/replaced/removed (null after deletion —
     * the trail outlives the document row).
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class, 'document_id');
    }

    /**
     * Who performed the action (system/admin on future imports; the tenant
     * themselves for the S4 self-service actions).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}