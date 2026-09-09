<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PropertyService
{
    /**
     * Find a property that belongs to the given owner, or 404.
     */
    public function findOwned(int $propertyId, User $owner): Property
    {
        $property = Property::with(['images', 'owner:id,name,email', 'history.changedBy:id,name'])
            ->where('owner_id', $owner->id)
            ->find($propertyId);

        if (! $property) {
            throw new NotFoundHttpException('Property not found.');
        }

        return $property;
    }

    /**
     * Create a property owned by the given owner.
     */
    public function create(array $data, User $owner): Property
    {
        $data['owner_id'] = $owner->id;

        return DB::transaction(function () use ($data) {
            return Property::create($data);
        });
    }

    /**
     * Update an owned property and return the refreshed model.
     */
    public function update(int $propertyId, array $data, User $owner): Property
    {
        $property = $this->findOwned($propertyId, $owner);

        DB::transaction(function () use ($property, $data) {
            $property->update($data);
        });

        return $property->fresh(['images', 'owner:id,name,email']);
    }

    /**
     * Delete an owned property. Returns true when found and deleted.
     */
    public function delete(int $propertyId, User $owner): bool
    {
        $property = $this->findOwned($propertyId, $owner);

        DB::transaction(function () use ($property) {
            $property->delete();
        });

        return true;
    }

    /**
     * Move a property to a new status through the explicit state machine.
     * Every successful transition writes a history row.
     */
    public function changeStatus(int $propertyId, string $newStatus, User $owner): Property
    {
        $property = $this->findOwned($propertyId, $owner);

        if (! $property->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move the property from {$property->status} to {$newStatus}.",
            ]);
        }

        DB::transaction(function () use ($property, $newStatus, $owner) {
            $fromStatus = $property->status;

            $property->update(['status' => $newStatus]);

            PropertyHistory::create([
                'property_id' => $property->id,
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $owner->id,
            ]);
        });

        return $property->fresh(['images', 'owner:id,name,email', 'history.changedBy:id,name']);
    }
}