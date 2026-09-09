<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\ViewingRequest;
use Inertia\Inertia;

class OwnerDashboardController extends Controller
{
    /**
     * Show the property owner dashboard.
     */
    public function index()
    {
        $user = auth()->user();

        $properties = Property::where('owner_id', $user->id)
            ->withCount('applications')
            ->latest()
            ->get();

        $rentDue = 0;
        foreach ($properties as $property) {
            if ($property->status === 'occupied' && $property->price) {
                $rentDue += (float) $property->price;
            }
        }

        $openEnquiries = Enquiry::whereHas('property', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', '!=', 'closed')
            ->count();

        $pendingViewings = ViewingRequest::whereHas('property', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('status', ['declined', 'completed', 'cancelled', 'no-show'])
            ->count();

        return Inertia::render('Owner/Dashboard', [
            'stats' => [
                'total_properties' => $properties->count(),
                'available' => $properties->where('status', 'available')->count(),
                'occupied' => $properties->where('status', 'occupied')->count(),
                'reserved' => $properties->where('status', 'reserved')->count(),
                'applications' => $properties->sum('applications_count'),
                'enquiries' => $openEnquiries,
                'viewings' => $pendingViewings,
                'rent_due' => $rentDue,
            ],
            'properties' => $properties,
        ]);
    }
}