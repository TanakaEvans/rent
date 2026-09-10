<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'listing_limit',
        'featured_slots',
        'support_tier',
        'analytics_enabled',
        'price',
        'billing_cycle',
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
            'listing_limit' => 'integer',
            'featured_slots' => 'integer',
            'analytics_enabled' => 'boolean',
            'price' => 'decimal:2',
            'billing_cycle' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * The subscriptions that reference this plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * The features granted by this plan (Feature Catalogue, Module 24).
     */
    public function features()
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features', 'plan_id', 'feature_id');
    }

    /**
     * Scope to plans owners are allowed to subscribe to.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}