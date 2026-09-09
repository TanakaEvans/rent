<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseSignature extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lease_id',
        'user_id',
        'signed_at',
        'signature_payload',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    /**
     * The lease this signature belongs to.
     */
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * The user who signed.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}