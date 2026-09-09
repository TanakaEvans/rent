<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\RentalApplication;
use App\Services\ApplicationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationService $service)
    {
    }

    /**
     * The tenant's applications with their current status.
     */
    public function tenantIndex(Request $request)
    {
        return Inertia::render('Tenant/Applications', [
            'applications' => $this->service->listForTenant($request->user()),
        ]);
    }

    /**
     * Submit an application for an available property (tenant).
     */
    public function store(Request $request, Property $property)
    {
        abort_unless($property->status === 'available', 404);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->create($request->user(), $property, $validated['message'] ?? null);

        return redirect()->back()->with('success', 'Application submitted — the owner will review it soon.');
    }

    /**
     * The owner review screen, grouped by property.
     */
    public function ownerIndex(Request $request)
    {
        return Inertia::render('Owner/Applications/Index', [
            'properties' => $this->service->listForOwner($request->user()),
        ]);
    }

    /**
     * Approve an application (owner). Property status stays untouched.
     */
    public function approve(Request $request, RentalApplication $application)
    {
        $this->service->approve($request->user(), $application->id);

        return redirect()->back()->with('success', 'Application approved.');
    }

    /**
     * Toggle an application between pending and shortlisted (owner).
     */
    public function toggleShortlist(Request $request, RentalApplication $application)
    {
        $application = $this->service->toggleShortlist($request->user(), $application->id);

        return redirect()->back()->with(
            'success',
            $application->status === 'shortlisted' ? 'Applicant shortlisted.' : 'Moved back to pending.'
        );
    }

    /**
     * Reject an application, with the required reason (owner).
     */
    public function reject(Request $request, RentalApplication $application)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->service->reject($request->user(), $application->id, $validated['reason']);

        return redirect()->back()->with('success', 'Application rejected.');
    }
}