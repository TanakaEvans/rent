<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionFeature extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'label',
        'description',
        'status',
    ];

    /**
     * The plans that grant this feature.
     */
    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'subscription_plan_features', 'feature_id', 'plan_id');
    }
}