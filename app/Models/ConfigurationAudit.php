<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurationAudit extends Model
{
    protected $table = 'configuration_audits';

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'configuration_id',
        'key',
        'old_value',
        'new_value',
        'changed_by',
        'reason',
        'approved_by',
        'effective_from',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
        ];
    }

    /**
     * The configuration key this audit row belongs to.
     */
    public function configuration()
    {
        return $this->belongsTo(SystemConfiguration::class);
    }

    /**
     * The user who made (and/or approved) the change.
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}