<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\ConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPlansController extends Controller
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

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

        $validated['listing_limit'] = isset($validated['listing_limit']) ? (int) $validated['listing_limit'] : null;
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

        if ($this->isDefaultPlan($plan) && $validated['name'] !== $plan->name) {
            throw ValidationException::withMessages([
                'name' => 'The '.$plan->name.' plan is the default plan new owners are placed on and cannot be renamed. Change the default plan in the Configuration Centre first.',
            ]);
        }

        $validated['listing_limit'] = isset($validated['listing_limit']) ? (int) $validated['listing_limit'] : null;
        $validated['featured_slots'] = (int) ($validated['featured_slots'] ?? 0);
        $validated['support_tier'] = $validated['support_tier'] ?? 'standard';

        $plan->update($validated);

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Subscription plan updated.');
    }

    /**
     * Archive a subscription plan. Plans still in use are archived (kept for
     * history and existing subscribers); unused plans are removed outright.
     * The configured default plan (`subscriptions.default_plan`) is never
     * archived or deleted — owners are auto-subscribed to it.
     */
    public function destroy(SubscriptionPlan $plan)
    {
        if ($this->isDefaultPlan($plan)) {
            throw ValidationException::withMessages([
                'plan' => 'The '.$plan->name.' plan is the default plan new owners are placed on and cannot be archived or deleted. Change the default plan in the Configuration Centre first.',
            ]);
        }

        if ($plan->subscriptions()->exists()) {
            $plan->update(['status' => 'archived']);
        } else {
            $plan->delete();
        }

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'Subscription plan removed.');
    }

    /**
     * Whether the plan is the one named by `subscriptions.default_plan`.
     */
    private function isDefaultPlan(SubscriptionPlan $plan): bool
    {
        return $plan->name === (string) $this->config->get('subscriptions.default_plan', 'Free');
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