<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnerSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Show the owner's current subscription, listing usage and the plan catalog.
     */
    public function index()
    {
        $owner = auth()->user();
        $subscription = $this->subscriptions->ensureFor($owner);

        return Inertia::render('Owner/Subscriptions/Index', [
            'current' => $subscription->load('plan.features'),
            'usage' => [
                'published' => $this->subscriptions->publishedCount($owner),
                'limit' => $this->subscriptions->effectiveLimit($owner),
            ],
            'plans' => SubscriptionPlan::active()->with('features')->orderBy('price')->get(),
            'invoices' => $subscription->invoices()->latest()->take(5)->get(),
            'proration_mode' => $this->subscriptions->prorationMode(),
        ]);
    }

    /**
     * Subscribe the owner to a plan (upgrade prorates, downgrade defers).
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer',
        ]);

        $this->subscriptions->subscribe($request->user(), (int) $validated['plan_id']);

        return redirect()->route('owner.subscriptions.index')
            ->with('success', 'Subscription updated.');
    }
}