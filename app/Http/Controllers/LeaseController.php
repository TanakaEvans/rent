<?php

namespace App\Http\Controllers;

use App\Models\RentalApplication;
use App\Services\LeaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaseController extends Controller
{
    public function __construct(private readonly LeaseService $service)
    {
    }

    /**
     * Generate a lease from a chosen approved application (owner).
     * Reservations and auto-rejections happen inside the service.
     */
    public function createFromApplication(Request $request, RentalApplication $application)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $this->service->createFromApplication($request->user(), $application, $validated);

        return redirect()->route('owner.leases.index')->with('success', 'Lease generated — the tenant has been notified.');
    }

    /**
     * The owner's leases across all their properties.
     */
    public function ownerIndex(Request $request)
    {
        return Inertia::render('Owner/Leases/Index', [
            'leases' => $this->service->listForOwner($request->user()),
        ]);
    }

    /**
     * The tenant's leases (draft agreement for review in this slice).
     */
    public function tenantIndex(Request $request)
    {
        return Inertia::render('Tenant/Leases', [
            'leases' => $this->service->listForTenant($request->user()),
        ]);
    }
}