<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Models\ViewingSlot;

class ViewingSlotService
{
    /**
     * The upcoming viewing slots for a property, earliest first.
     */
    public function slotsFor(User $user, Property $property)
    {
        $this->authorizeOwner($user, $property);

        return ViewingSlot::where('property_id', $property->id)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ViewingSlot $slot) => $slot->setAttribute('is_past', $slot->ends_at->lt(now())));
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
     * Update the times of an available slot on the owner's property.
     */
    public function update(User $user, Property $property, ViewingSlot $slot, array $validated): ViewingSlot
    {
        $this->authorizeOwner($user, $property);
        $this->authorizeSlot($slot, $property);

        $slot->update([
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ]);

        return $slot;
    }

    /**
     * Delete an available slot that belongs to the property.
     */
    public function destroy(User $user, Property $property, ViewingSlot $slot): void
    {
        $this->authorizeOwner($user, $property);
        $this->authorizeSlot($slot, $property);

        $slot->delete();
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