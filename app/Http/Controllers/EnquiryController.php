<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\EnquiryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnquiryController extends Controller
{
    public function __construct(private readonly EnquiryService $service)
    {
    }

    /**
     * Create an enquiry for an available property (tenant).
     */
    public function store(Request $request, Property $property)
    {
        abort_unless($property->status === 'available', 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $this->service->create($request->user(), $property, $validated);

        return redirect()->back()->with('success', 'Enquiry sent — the owner will respond shortly.');
    }

    /**
     * The tenant's enquiry threads.
     */
    public function tenantIndex(Request $request)
    {
        return Inertia::render('Tenant/Enquiries', [
            'enquiries' => $this->service->listForTenant($request->user()),
        ]);
    }

    /**
     * The owner inbox, grouped by property.
     */
    public function ownerIndex(Request $request)
    {
        return Inertia::render('Owner/Enquiries/Index', [
            'properties' => $this->service->inboxForOwner($request->user()),
        ]);
    }

    /**
     * A single enquiry thread for the owner (marks it read).
     */
    public function ownerShow(Request $request, int $id)
    {
        $enquiry = $this->service->openForOwner($request->user(), $id);

        return Inertia::render('Owner/Enquiries/Show', [
            'enquiry' => $enquiry,
        ]);
    }

    /**
     * Reply to an enquiry as the owner.
     */
    public function reply(Request $request, int $id)
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ]);

        $this->service->reply($request->user(), $id, $validated['reply']);

        return redirect()->back()->with('success', 'Reply sent to the tenant.');
    }

    /**
     * Close an enquiry thread as the owner.
     */
    public function close(Request $request, int $id)
    {
        $this->service->close($request->user(), $id);

        return redirect()->back()->with('success', 'Enquiry closed.');
    }
}