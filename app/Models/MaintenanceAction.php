<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceAction extends Model
{
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'actor_id',
        'action',
        'notes',
    ];

    /**
     * The maintenance request this action belongs to.
     */
    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    /**
     * The user who performed the action (null for system sweeps).
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}