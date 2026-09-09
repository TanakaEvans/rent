<?php

namespace App\Http\Controllers;

use App\Models\RentalApplication;
use Inertia\Inertia;

class TenantDashboardController extends Controller
{
    /**
     * Show the tenant dashboard.
     */
    public function index()
    {
        $user = auth()->user();

        $favourites = $user->favouritedProperties()
            ->with('owner:id,name,email')
            ->latest()
            ->get();

        $applications = RentalApplication::where('applicant_id', $user->id)
            ->with('property:id,title,price,property_type,suburb,city,cover_image')
            ->latest()
            ->get();

        $enquiries = $user->enquiries()->count();

        return Inertia::render('Tenant/Dashboard', [
            'stats' => [
                'favourites' => $favourites->count(),
                'applications' => $applications->count(),
                'pending' => $applications->where('status', 'pending')->count(),
                'approved' => $applications->where('status', 'approved')->count(),
                'enquiries' => $enquiries,
            ],
            'favourites' => $favourites,
            'applications' => $applications,
        ]);
    }
}