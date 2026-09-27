<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Services\ViewingSlotService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewingSlotController extends Controller
{
    public function __construct(private readonly ViewingSlotService $service)
    {
    }

    /**
     * Manage the viewing slots for one of the owner's properties.
     */
    public function index(Request $request, Property $property)
    {
        abort_unless($property->owner_id === $request->user()->id, 404);

        // Pending tenant requests (including tenant-suggested times) so the
        // owner sees demand right on the calendar.
        $requests = ViewingRequest::where('property_id', $property->id)
            ->whereIn('status', ['requested', 'accepted', 'rescheduled'])
            ->with('tenant:id,name')
            ->latest()
            ->get()
            ->map(function (ViewingRequest $req) {
                $window = $req->window();

                return [
                    'id' => $req->id,
                    'status' => $req->status,
                    'is_proposed' => $req->slot_id === null,
                    'tenant' => $req->tenant?->name,
                    'starts_at' => $window['start'],
                    'ends_at' => $window['end'],
                    'message' => $req->request_message,
                ];
            });

        return Inertia::render('Owner/ViewingSlots/Index', [
            'property' => $property,
            'slots' => $this->service->slotsFor($request->user(), $property),
            'requests' => $requests,
        ]);
    }

    /**
     * Create an available viewing slot.
     */
    public function store(Request $request, Property $property)
    {
        $validated = $this->validateSlot($request);

        $this->service->create($request->user(), $property, $validated);

        return redirect()->back()->with('success', 'Viewing slot added.');
    }

    /**
     * Update the times of an available viewing slot.
     */
    public function update(Request $request, Property $property, ViewingSlot $slot)
    {
        $validated = $this->validateSlot($request);

        $this->service->update($request->user(), $property, $slot, $validated);

        return redirect()->back()->with('success', 'Viewing slot updated.');
    }

    /**
     * Delete an available viewing slot.
     */
    public function destroy(Request $request, Property $property, ViewingSlot $slot)
    {
        $this->service->destroy($request->user(), $property, $slot);

        return redirect()->back()->with('success', 'Viewing slot removed.');
    }

    private function validateSlot(Request $request): array
    {
        return $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
    }
}