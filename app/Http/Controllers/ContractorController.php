<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use App\Services\ContractorService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The contractor registry (Module 11) and the contractor "my jobs" desk.
 */
class ContractorController extends Controller
{
    public function __construct(
        private readonly ContractorService $service,
        private readonly MaintenanceService $maintenance
    ) {
    }

    /**
     * The admin registry: register tradespeople and move their status along
     * the unverified -> vetting -> verified -> suspended machine.
     */
    public function adminIndex()
    {
        return Inertia::render('Admin/Contractors/Index', [
            'contractors' => $this->service->listForAdmin(),
            'stats' => $this->service->registryStats(),
            'transitions' => Contractor::TRANSITIONS,
        ]);
    }

    /**
     * Register a tradesperson (always lands in `vetting` until staff reviews).
     */
    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:120'],
            'service_area' => ['required', 'array', 'min:1'],
            'service_area.*' => ['required', 'string', 'max:60'],
            'trades' => ['required', 'array', 'min:1'],
            'trades.*.trade' => ['required', 'string', 'max:60'],
            'trades.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'user_id' => ['nullable', 'integer', 'exists:auth_users,id'],
            'user_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $contractor = $this->service->register($validated, $request->user());

        return redirect()->back()->with('success', $contractor->user_id !== null
            ? 'Contractor registered and login linked — profile is now under vetting.'
            : 'Contractor registered — profile is now under vetting.');
    }

    /**
     * Link an existing account (by email) to a contractor profile and grant
     * it the Contractor role (opens the "My Jobs" desk).
     */
    public function adminLink(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'user_email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $this->service->linkLogin($contractor, $validated['user_email'], $request->user());

        return redirect()->back()->with('success', 'Login linked — the account can now open My Jobs.');
    }

    /**
     * Move a contractor's registry status (verification / suspension).
     */
    public function adminStatus(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:unverified,vetting,verified,suspended'],
        ]);

        $this->service->setStatus($contractor, $validated['status']);

        return redirect()->back()->with('success', 'Contractor status updated.');
    }

    /**
     * The contractor's job brief desk (their assigned maintenance jobs).
     */
    public function contractorIndex(Request $request)
    {
        $profile = Contractor::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Contractor/Maintenance/Index', [
            'requests' => $this->maintenance->listForContractor($request->user()),
            'profile' => $profile,
        ]);
    }

    /**
     * The contractor begins work on an assigned job (assigned -> in_progress).
     */
    public function startJob(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->maintenance->start($request->user(), $maintenanceRequest);

        return redirect()->back()->with('success', 'Job started — the owner has been notified.');
    }

    /**
     * The contractor reports the work done (in_progress -> completed) with a
     * completion summary. Pages the owner and the tenant for confirmation.
     */
    public function completeJob(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $this->maintenance->complete($request->user(), $maintenanceRequest, $validated);

        return redirect()->back()->with('success', 'Work marked complete — awaiting tenant confirmation.');
    }
}