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
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly ConfigurationService $config,
        private readonly ListingLifecycleService $lifecycle,
        private readonly MarketplaceAlertService $alerts,
    ) {
    }

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
     * Create a property owned by the given owner. Publishing straight to
     * "available" is gated on the owner's subscription quota (FR-03 / AC-01),
     * stamps the listing expiry window and fires saved-search match alerts.
     */
    public function create(array $data, User $owner): Property
    {
        $data['owner_id'] = $owner->id;

        if (($data['status'] ?? null) === 'available' && ! $this->subscriptions->hasQuota($owner, 1)) {
            throw $this->quotaError();
        }

        $property = DB::transaction(function () use ($data) {
            if (($data['status'] ?? null) === 'available') {
                $data['expires_at'] = now()->addDays($this->lifecycle->validityDays());
            }

            return Property::create($data);
        });

        $this->alerts->notifyNewMatches($property);

        return $property;
    }

    /**
     * Update an owned property and return the refreshed model. A rent
     * reduction fires price-drop alerts to tenants who saved the property.
     */
    public function update(int $propertyId, array $data, User $owner): Property
    {
        $property = $this->findOwned($propertyId, $owner);
        $oldPrice = (float) $property->price;

        DB::transaction(function () use ($property, $data) {
            $property->update($data);
        });

        if (array_key_exists('price', $data) && (float) $data['price'] !== $oldPrice) {
            $this->alerts->notifyPriceDrop($property->fresh(), $oldPrice, (float) $data['price']);
        }

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

        if ($newStatus === 'available' && ! $this->subscriptions->hasQuota($owner, 1)) {
            throw $this->quotaError();
        }

        if (! $property->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move the property from {$property->status} to {$newStatus}.",
            ]);
        }

        DB::transaction(function () use ($property, $newStatus, $owner) {
            $fromStatus = $property->status;

            $property->update([
                'status' => $newStatus,
                'expires_at' => $newStatus === 'available'
                    ? now()->addDays($this->lifecycle->validityDays())
                    : $property->expires_at,
            ]);

            PropertyHistory::create([
                'property_id' => $property->id,
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $owner->id,
            ]);
        });

        if ($newStatus === 'available') {
            $this->alerts->notifyNewMatches($property->fresh());
            $this->alerts->notifyAvailability($property->fresh());
        }

        return $property->fresh(['images', 'owner:id,name,email', 'history.changedBy:id,name']);
    }

    /**
     * Validation error raised when the owner is over their listing quota.
     */
    private function quotaError(): ValidationException
    {
        return ValidationException::withMessages([
            'status' => $this->config->get('subscriptions.entitlement_over_limit_message', "You've reached your plan's listing limit. Upgrade your subscription to publish more properties."),
        ]);
    }
}