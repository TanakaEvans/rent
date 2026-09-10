<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single detail-page view of a property. Anonymous views carry an IP;
 * logged-in tenants carry a `user_id` so "recently viewed" works per tenant.
 */
class PropertyView extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'user_id',
        'ip',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function viewer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}