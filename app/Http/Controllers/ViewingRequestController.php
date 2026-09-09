<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Services\ViewingRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewingRequestController extends Controller
{
    public function __construct(private readonly ViewingRequestService $requests)
    {
    }

    /**
     * The viewing requests across the owner's properties.
     */
    public function indexOwner(Request $request)
    {
        return Inertia::render('Owner/Viewings/Index', [
            'requests' => $this->requests->requestsForOwner($request->user()),
        ]);
    }

    /**
     * The viewing requests made by the tenant.
     */
    public function indexTenant(Request $request)
    {
        return Inertia::render('Tenant/Viewings', [
            'requests' => $this->requests->requestsForTenant($request->user()),
        ]);
    }

    /**
     * A tenant requests a viewing on an available slot.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'slot_id' => ['required', 'integer'],
            'request_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        $booking = $this->requests->create(
            $request->user(),
            $property,
            ViewingSlot::findOrFail($validated['slot_id']),
            $validated['request_message'] ?? null,
        );

        return redirect()->back()->with('success', 'Viewing request sent. The owner will confirm your slot.');
    }

    /**
     * Owner accepts a request and locks the slot.
     */
    public function accept(Request $request, ViewingRequest $booking)
    {
        $this->requests->accept($request->user(), $booking);

        return redirect()->back()->with('success', 'Viewing confirmed and slot locked.');
    }

    /**
     * Owner declines a request.
     */
    public function decline(Request $request, ViewingRequest $booking)
    {
        $this->requests->decline($request->user(), $booking);

        return redirect()->back()->with('success', 'Viewing request declined.');
    }

    /**
     * Owner proposes a different slot for a running booking.
     */
    public function reschedule(Request $request, ViewingRequest $booking)
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer'],
        ]);

        $this->requests->reschedule($request->user(), $booking, ViewingSlot::findOrFail($validated['slot_id']));

        return redirect()->back()->with('success', 'New slot proposed. The tenant must confirm it.');
    }

    /**
     * Tenant confirms the rescheduled slot.
     */
    public function confirm(Request $request, ViewingRequest $booking)
    {
        $this->requests->confirm($request->user(), $booking);

        return redirect()->back()->with('success', 'Slot confirmed.');
    }

    /**
     * Either party cancels a running booking.
     */
    public function cancel(Request $request, ViewingRequest $booking)
    {
        $this->requests->cancel($request->user(), $booking);

        return redirect()->back()->with('success', 'Viewing cancelled.');
    }

    /**
     * Owner marks the viewing completed.
     */
    public function complete(Request $request, ViewingRequest $booking)
    {
        $this->requests->complete($request->user(), $booking);

        return redirect()->back()->with('success', 'Viewing marked completed.');
    }

    /**
     * Owner records a tenant no-show.
     */
    public function noShow(Request $request, ViewingRequest $booking)
    {
        $this->requests->markNoShow($request->user(), $booking);

        return redirect()->back()->with('success', 'Marked as no-show.');
    }
}