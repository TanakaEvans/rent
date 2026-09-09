<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Notifications\ViewingAcceptedNotification;
use App\Notifications\ViewingRequestedNotification;
use App\Notifications\ViewingRescheduledNotification;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ViewingRequestService
{
    /**
     * A tenant requests a viewing on an available future slot.
     */
    public function create(User $tenant, Property $property, ViewingSlot $slot, ?string $message): ViewingRequest
    {
        abort_unless($property->status === 'available', 404);
        $this->authorizeSlotOnProperty($slot, $property);
        $this->assertSlotBookable($slot);

        if (ViewingRequest::where('tenant_id', $tenant->id)->where('slot_id', $slot->id)->active()->exists()) {
            throw new ConflictHttpException('You already have an active request on this slot.');
        }

        $booking = ViewingRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'slot_id' => $slot->id,
            'request_message' => $message,
            'status' => 'requested',
        ]);

        $property->owner->notify(new ViewingRequestedNotification($booking));

        return $booking;
    }

    /**
     * All viewing bookings against the owner's properties, newest first.
     * Each booking carries its property's other available slots for rescheduling.
     */
    public function requestsForOwner(User $owner)
    {
        $slots = ViewingSlot::whereHas('property', fn ($q) => $q->where('owner_id', $owner->id))
            ->where('status', 'available')
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();

        return ViewingRequest::whereHas('property', fn ($q) => $q->where('owner_id', $owner->id))
            ->with(['property', 'tenant', 'slot'])
            ->latest()
            ->get()
            ->map(function (ViewingRequest $request) use ($slots) {
                $slotsForProperty = collect($slots->where('property_id', $request->property_id)->values());

                return $request->setAttribute('available_slots', $slotsForProperty->map(fn (ViewingSlot $s) => [
                    'id' => $s->id,
                    'starts_at' => $s->starts_at,
                    'ends_at' => $s->ends_at,
                ]));
            });
    }

    /**
     * All viewing bookings made by the tenant, newest first.
     */
    public function requestsForTenant(User $tenant)
    {
        return ViewingRequest::with(['property', 'slot'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
    }

    /**
     * Owner accepts a requested viewing; the slot is locked (double-book 409).
     */
    public function accept(User $owner, ViewingRequest $request): ViewingRequest
    {
        $this->authorizeOwner($owner, $request);
        $this->assertTransition($request, 'accept');

        $this->assertSlotBookable($request->slot);

        $request->slot->update(['status' => 'taken']);
        $request->update(['status' => 'accepted']);

        $request->tenant->notify(new ViewingAcceptedNotification($request));

        return $request;
    }

    /**
     * Owner declines a requested (or rescheduled) viewing.
     */
    public function decline(User $owner, ViewingRequest $request): ViewingRequest
    {
        $this->authorizeOwner($owner, $request);
        $this->assertTransition($request, 'decline');

        $this->releaseSlot($request);

        $request->update(['status' => 'declined']);

        return $request;
    }

    /**
     * Owner proposes a different slot; the request waits for tenant confirmation.
     */
    public function reschedule(User $owner, ViewingRequest $request, ViewingSlot $newSlot): ViewingRequest
    {
        $this->authorizeOwner($owner, $request);
        $this->assertTransition($request, 'reschedule');
        $this->authorizeSlotOnProperty($newSlot, $request->property);
        $this->assertSlotBookable($newSlot);

        $this->releaseSlot($request);
        $request->update([
            'slot_id' => $newSlot->id,
            'status' => 'rescheduled',
        ]);

        $request->tenant->notify(new ViewingRescheduledNotification($request));

        return $request;
    }

    /**
     * Tenant confirms the rescheduled slot; it is now locked.
     */
    public function confirm(User $tenant, ViewingRequest $request): ViewingRequest
    {
        abort_unless($request->tenant_id === $tenant->id, 404);
        $this->assertTransition($request, 'confirm');

        $this->assertSlotBookable($request->slot);

        $request->slot->update(['status' => 'taken']);
        $request->update(['status' => 'accepted']);

        return $request;
    }

    /**
     * Either party cancels a running booking; a locked slot is freed.
     */
    public function cancel(User $user, ViewingRequest $request): ViewingRequest
    {
        $isTenant = $request->tenant_id === $user->id;
        $isOwner = $request->property->owner_id === $user->id;

        abort_unless($isTenant || $isOwner, 404);
        abort_unless(in_array('cancel', ViewingRequest::TRANSITIONS[$request->status] ?? [], true), 409);

        $this->releaseSlot($request);
        $request->update(['status' => 'cancelled']);

        return $request;
    }

    /**
     * Owner records the viewing as completed.
     */
    public function complete(User $owner, ViewingRequest $request): ViewingRequest
    {
        $this->authorizeOwner($owner, $request);
        $this->assertTransition($request, 'complete');

        $request->update(['status' => 'completed']);

        return $request;
    }

    /**
     * Owner marks the tenant as a no-show; the slot frees up again.
     */
    public function markNoShow(User $owner, ViewingRequest $request): ViewingRequest
    {
        $this->authorizeOwner($owner, $request);
        $this->assertTransition($request, 'no-show');

        $this->releaseSlot($request);
        $request->update(['status' => 'no-show']);

        return $request;
    }

    private function authorizeOwner(User $owner, ViewingRequest $request): void
    {
        abort_unless($request->property->owner_id === $owner->id, 404);
    }

    private function authorizeSlotOnProperty(ViewingSlot $slot, Property $property): void
    {
        abort_unless($slot->property_id === $property->id, 404);
    }

    private function assertSlotBookable(ViewingSlot $slot): void
    {
        abort_unless($slot->status === 'available' && $slot->ends_at->isFuture(), 409);
    }

    private function assertTransition(ViewingRequest $request, string $transition): void
    {
        abort_unless(in_array($transition, ViewingRequest::TRANSITIONS[$request->status] ?? [], true), 409);
    }

    private function releaseSlot(ViewingRequest $request): void
    {
        if ($request->slot->status === 'taken') {
            $request->slot->update(['status' => 'available']);
        }
    }
}