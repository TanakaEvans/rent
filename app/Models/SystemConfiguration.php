<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'group_name',
        'key',
        'type',
        'value',
        'label',
        'description',
        'risk',
        'is_editable',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'string',
            'is_editable' => 'boolean',
        ];
    }

    /**
     * The audit rows written for this configuration key.
     */
    public function audits()
    {
        return $this->hasMany(ConfigurationAudit::class);
    }
}