<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;

class FavouriteService
{
    /**
     * Toggle a property in the user's favourites (idempotent: never duplicates,
     * never errors when removing a non-favourite).
     */
    public function toggle(User $user, int $propertyId): Property
    {
        $property = Property::findOrFail($propertyId);

        $user->favouritedProperties()->toggle($propertyId);

        return $property;
    }

    /**
     * The user's favourited properties, newest favourite first.
     */
    public function listFor(User $user)
    {
        return $user->favouritedProperties()
            ->with('owner:id,name,email')
            ->latest();
    }

    /**
     * Property ids favourited by the user, for pre-filling icon state.
     *
     * @return array<int>
     */
    public function idsFor(User $user): array
    {
        return $user->favouritedProperties()->pluck('properties.id')->all();
    }
}