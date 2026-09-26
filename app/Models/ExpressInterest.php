<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressInterest extends Model
{
    /**
     * Queue lifecycle (S6). `interested` is the tenant's expressed interest;
     * `contacted` means the owner reached out; `archived` removes it from the
     * active queue but the row stays (a re-express re-opens it to
     * `interested`).
     *
     *   interested → contacted | archived
     *   contacted  → interested | archived
     *   archived   → interested   (only via a tenant re-express)
     */
    public const STATUSES = ['interested', 'contacted', 'archived'];

    public const TRANSITIONS = [
        'interested' => ['contacted', 'archived'],
        'contacted' => ['interested', 'archived'],
        'archived' => ['interested'],
    ];

    /**
     * Whether the row may move to the given status (owner actions use the
     * model; a tenant re-express moves an archived row back to interested).
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'property_id',
        'tenant_id',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}