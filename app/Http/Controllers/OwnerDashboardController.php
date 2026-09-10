<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\ViewingRequest;
use App\Services\FinancialSummaryService;
use App\Services\SubscriptionService;
use Inertia\Inertia;

class OwnerDashboardController extends Controller
{
    /**
     * Show the property owner dashboard.
     */
    public function index(SubscriptionService $subscriptions, FinancialSummaryService $financial)
    {
        $user = auth()->user();

        $properties = Property::where('owner_id', $user->id)
            ->withCount('applications')
            ->latest()
            ->get();

        $openEnquiries = Enquiry::whereHas('property', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', '!=', 'closed')
            ->count();

        $pendingViewings = ViewingRequest::whereHas('property', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('status', ['declined', 'completed', 'cancelled', 'no-show'])
            ->count();

        $current = $subscriptions->ensureFor($user);
        $finances = $financial->ownerIndex($user);

        return Inertia::render('Owner/Dashboard', [
            'stats' => [
                'total_properties' => $properties->count(),
                'available' => $properties->where('status', 'available')->count(),
                'occupied' => $properties->where('status', 'occupied')->count(),
                'reserved' => $properties->where('status', 'reserved')->count(),
                'applications' => $properties->sum('applications_count'),
                'enquiries' => $openEnquiries,
                'viewings' => $pendingViewings,
                'rent_due' => $finances['outstanding_total'],
                'monthly_income' => $finances['monthly_income'],
                'occupancy_rate' => $finances['occupancy_rate'],
            ],
            'financial' => $finances,
            'subscription' => [
                'plan_name' => $current->plan?->name,
                'price' => $current->plan?->price,
                'billing_cycle' => $current->cycle,
                'status' => $current->status,
                'ends_at' => $current->ends_at?->toDateString(),
                'usage' => $subscriptions->publishedCount($user),
                'limit' => $subscriptions->effectiveLimit($user),
            ],
            'properties' => $properties,
        ]);
    }
}