<?php

namespace App\Services;

use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Marketplace alerting: match alerts for saved searches (new listing) and
 * price-drop / back-on-market alerts for favourited properties. Every send
 * is deduplicated so a notification is not spam-repeated within 24h.
 */
class MarketplaceAlertService
{
    public function __construct(private readonly SavedSearchService $searches)
    {
    }

    /**
     * After a property is published, notify tenants whose alert-enabled
     * saved searches match it.
     */
    public function notifyNewMatches(Property $property): void
    {
        if ($property->status !== 'available') {
            return;
        }

        foreach ($this->searches->alertable() as $search) {
            if ($this->searches->matches($property, (array) $search->criteria)) {
                $this->dedupe($search->user, \App\Notifications\SavedSearchMatchNotification::class)
                    ?->notify(new \App\Notifications\SavedSearchMatchNotification($property, $search));
            }
        }
    }

    /**
     * When a favourited listing's price drops, tell everyone who saved it.
     */
    public function notifyPriceDrop(Property $property, float $oldAmount, float $newAmount): void
    {
        if ($newAmount >= $oldAmount || $property->status !== 'available') {
            return;
        }

        foreach ($property->favouritedBy()->get() as $favourite) {
            $this->dedupe($favourite, \App\Notifications\PriceDropNotification::class)
                ?->notify(new \App\Notifications\PriceDropNotification($property, $oldAmount, $newAmount));
        }
    }

    /**
     * When a favourite becomes available again (listing added anew or a
     * listing returns to the market), notify everyone who saved it.
     */
    public function notifyAvailability(Property $property): void
    {
        if ($property->status !== 'available') {
            return;
        }

        foreach ($property->favouritedBy()->get() as $favourite) {
            $this->dedupe($favourite, \App\Notifications\AvailabilityAlertNotification::class)
                ?->notify(new \App\Notifications\AvailabilityAlertNotification($property));
        }
    }

    /**
     * Skip a send when the same notification type already exists for this
     * user within the last 24 hours (returns null to short-circuit).
     */
    private function dedupe(User $notifiable, string $type): ?User
    {
        $recent = DatabaseNotification::query()
            ->where('notifiable_id', $notifiable->id)
            ->where('type', $type)
            ->where('created_at', '>', now()->subDay())
            ->exists();

        return $recent ? null : $notifiable;
    }
}