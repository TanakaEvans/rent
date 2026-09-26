<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Models\ViewingSlot;

class ViewingSlotService
{
    /**
     * The viewing slots for a property, earliest first. Each slot carries
     * `is_past` (already finished) and `is_editable` (the owner may still
     * change or delete it) so the page mirrors the server guard.
     */
    public function slotsFor(User $user, Property $property)
    {
        $this->authorizeOwner($user, $property);

        return ViewingSlot::where('property_id', $property->id)
            ->withCount([
                'requests',
                'requests as active_requests_count' => fn ($query) => $query->active(),
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ViewingSlot $slot) {
                $slot->setAttribute('is_past', $slot->ends_at->lt(now()));
                $slot->setAttribute('is_editable', $this->blockReason($slot, 'update') === null);
                $slot->setAttribute('is_deletable', $this->blockReason($slot, 'delete') === null);

                return $slot;
            });
    }

    /**
     * Create an available viewing slot on one of the owner's properties.
     */
    public function create(User $user, Property $property, array $validated): ViewingSlot
    {
        $this->authorizeOwner($user, $property);

        return ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'status' => 'available',
        ]);
    }

    /**
     * Update the times of an open, future slot on the owner's property.
     */
    public function update(User $user, Property $property, ViewingSlot $slot, array $validated): ViewingSlot
    {
        $this->authorizeOwner($user, $property);
        $this->authorizeSlot($slot, $property);
        $this->assertChangeable($slot, 'update');

        $slot->update([
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ]);

        return $slot;
    }

    /**
     * Delete an open, future slot that no tenant has ever requested.
     */
    public function destroy(User $user, Property $property, ViewingSlot $slot): void
    {
        $this->authorizeOwner($user, $property);
        $this->authorizeSlot($slot, $property);
        $this->assertChangeable($slot, 'delete');

        $slot->delete();
    }

    /**
     * Only an available slot that has not started can change. A slot with
     * running requests cannot move under the tenant, and a slot with any
     * booking history is kept for the record.
     */
    private function blockReason(ViewingSlot $slot, string $action): ?string
    {
        if ($slot->status !== 'available') {
            return 'This viewing time is booked. Cancel or reschedule the viewing first.';
        }

        if (! $slot->starts_at->isFuture()) {
            return 'This viewing time has already started or passed and can no longer be changed.';
        }

        $active = $slot->active_requests_count ?? $slot->requests()->active()->count();
        if ($active > 0) {
            return 'A tenant has requested this time. Accept, decline or reschedule the request first.';
        }

        $any = $slot->requests_count ?? $slot->requests()->count();
        if ($action === 'delete' && $any > 0) {
            return 'This viewing time has booking history and is kept for the record.';
        }

        return null;
    }

    private function assertChangeable(ViewingSlot $slot, string $action): void
    {
        $reason = $this->blockReason($slot, $action);

        abort_if($reason !== null, 409, $reason ?? '');
    }

    /**
     * Ensure the user owns the property (404, never reveal other owners' data).
     */
    private function authorizeOwner(User $user, Property $property): void
    {
        abort_unless($property->owner_id === $user->id, 404);
    }

    /**
     * Ensure the slot actually belongs to the given property.
     */
    private function authorizeSlot(ViewingSlot $slot, Property $property): void
    {
        abort_unless($slot->property_id === $property->id, 404);
    }
}
