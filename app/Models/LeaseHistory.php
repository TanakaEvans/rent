<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseHistory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lease_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lease_id',
        'action',
        'performed_by',
        'details',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /**
     * The lease this entry belongs to.
     */
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * The user who performed the action (if recorded).
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}