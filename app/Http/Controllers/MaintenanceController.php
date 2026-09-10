<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Services\ContractorService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $service,
        private readonly ContractorService $contractors
    ) {
    }

    /**
     * The tenant's maintenance hub: report against a leased property and
     * track every request with its SLA clock.
     */
    public function tenantIndex(Request $request)
    {
        return Inertia::render('Tenant/Maintenance', [
            'requests' => $this->service->listForTenant($request->user()),
            'properties' => $this->service->reportableProperties($request->user()),
            'categories' => (array) app(\App\Services\ConfigurationService::class)
                ->get('maintenance.categories', ['plumbing', 'electrical', 'appliance', 'structural', 'pest', 'safety', 'other']),
        ]);
    }

    /**
     * File a maintenance request (tenant). Only an actively leased property
     * is reportable — anything else resolves to the service's 404.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'category' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high,emergency'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $this->service->create($request->user(), $validated);

        return redirect()->back()->with('success', 'Maintenance request submitted — the owner has been notified.');
    }

    /**
     * The owner's maintenance triage desk for their properties.
     */
    public function ownerIndex(Request $request)
    {
        return Inertia::render('Owner/Maintenance/Index', [
            'requests' => $this->service->listForOwner($request->user()),
            'contractors' => $this->contractors->verifiedForAssign($request->user()),
        ]);
    }

    /**
     * Commit a verified contractor to a reported request with the agreed
     * quote (owner). Only the property's owner may assign; only verified
     * contractors are assignable; a request already assigned blocks re-assign.
     */
    public function ownerAssign(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'approved_quote' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $this->service->assignToContractor($request->user(), $maintenanceRequest, $validated);

        return redirect()->back()->with('success', 'Contractor assigned — the job brief has been sent.');
    }

    /**
     * The staff escalation queue for breached first-response SLAs.
     */
    public function adminEscalations(Request $request)
    {
        return Inertia::render('Admin/Maintenance/Escalations', [
            'requests' => $this->service->listEscalationsForAdmin(),
        ]);
    }

    /**
     * Take ownership of an escalated request (staff).
     */
    public function adminAcknowledge(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->service->acknowledge($maintenanceRequest, $request->user());

        return redirect()->back()->with('success', 'Escalation acknowledged — the case stays in the owner queue.');
    }

    /**
     * The tenant confirms the completed fix (tenant) — the gate the owner
     * needs before the request can be closed.
     */
    public function tenantConfirm(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->service->confirm($request->user(), $maintenanceRequest);

        return redirect()->back()->with('success', 'Fix confirmed — the owner can now close the request.');
    }

    /**
     * The owner closes the request after inspection (owner). Requires the
     * tenant to have confirmed the fix first.
     */
    public function ownerClose(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->close($request->user(), $maintenanceRequest, $validated);

        return redirect()->back()->with('success', 'Request closed — it now feeds maintenance reporting.');
    }

    /**
     * The owner rates the contractor behind a closed request (owner). The
     * aggregate rating on the contractor is recomputed server-side.
     */
    public function ownerRate(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->contractors->rate($request->user(), $maintenanceRequest, $validated);

        return redirect()->back()->with('success', 'Contractor rated — their average has been updated.');
    }
}