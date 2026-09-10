<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class AdminPlansController extends Controller
{
    /**
     * List subscription plans with subscriber counts.
     */
    public function index()
    {
        $plans = SubscriptionPlan::withCount(['subscriptions as active_subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])->orderBy('price')->get();

        return inertia('Admin/Subscriptions/Plans', [
            'plans' => $plans,
        ]);
    }

    /**
     * Create a subscription plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:subscription_plans,name',
            'listing_limit' => 'nullable|integer|min:0',
            'featured_slots' => 'nullable|integer|min:0',
            'support_tier' => 'nullable|string|max:30',
            'analytics_enabled' => 'boolean',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $validated['listing_limit'] = $validated['listing_limit'] === null ? null : (int) $validated['listing_limit'];
        $validated['featured_slots'] = (int) ($validated['featured_slots'] ?? 0);
        $validated['support_tier'] = $validated['support_tier'] ?? 'standard';

        SubscriptionPlan::create($validated + ['status' => 'active']);

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Subscription plan created.');
    }

    /**
     * Update a subscription plan.
     */
    public function update(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:subscription_plans,name,'.$plan->id,
            'listing_limit' => 'nullable|integer|min:0',
            'featured_slots' => 'nullable|integer|min:0',
            'support_tier' => 'nullable|string|max:30',
            'analytics_enabled' => 'boolean',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $validated['listing_limit'] = $validated['listing_limit'] === null ? null : (int) $validated['listing_limit'];
        $validated['featured_slots'] = (int) ($validated['featured_slots'] ?? 0);
        $validated['support_tier'] = $validated['support_tier'] ?? 'standard';

        $plan->update($validated);

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Subscription plan updated.');
    }

    /**
     * Archive a subscription plan. Plans still in use are archived (kept for
     * history and existing subscribers); unused plans are removed outright.
     */
    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['status' => 'archived']);
        } else {
            $plan->delete();
        }

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Subscription plan removed.');
    }

    /**
     * Toggle which Feature Catalogue items a plan grants (Module 24).
     */
    public function features(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'feature_ids' => 'array',
            'feature_ids.*' => 'integer|exists:subscription_features,id',
        ]);

        $plan->features()->sync($validated['feature_ids'] ?? []);

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Plan features updated.');
    }
}