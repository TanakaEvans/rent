<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Listing lifecycle: automatic expiry, expiry reminders and renewal
 * (Marketplace §40/§41). Validity and reminders are rules as data —
 * `listings.validity_days`, `listings.validity_reminders`,
 * `listings.auto_expire` and `listings.renew_grace_days`.
 */
class ListingLifecycleService
{
    public function __construct(
        private readonly ConfigurationService $config,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    public function validityDays(): int
    {
        return max(7, min(365, (int) $this->config->get('listings.validity_days', 60)));
    }

    public function graceDays(): int
    {
        return max(0, min(30, (int) $this->config->get('listings.renew_grace_days', 7)));
    }

    /**
     * Expire every listed property past its `expires_at` (unless the
     * scheduler auto-expiry is switched off). Returns the count expired.
     */
    public function expireDue(): int
    {
        if (! (bool) $this->config->get('listings.auto_expire', true)) {
            return 0;
        }

        $due = Property::listed()->where('expires_at', '<', now())->get();
        $count = 0;

        foreach ($due as $property) {
            DB::transaction(function () use ($property) {
                $property->update(['status' => 'unavailable']);
                PropertyHistory::create([
                    'property_id' => $property->id,
                    'from_status' => 'available',
                    'to_status' => 'unavailable',
                    'changed_by' => null,
                    'note' => 'Listing expired',
                ]);
            });
            $count++;
        }

        return $count;
    }

    /**
     * Listed properties due to expire within the given number of days,
     * used by the reminder scheduler.
     */
    public function dueSoon(int $withinDays)
    {
        return Property::listed()
            ->with('owner:id,name,email')
            ->whereBetween('expires_at', [now(), now()->addDays($withinDays)])
            ->get();
    }

    /**
     * Renew a listing for another validity period. Works for live listings
     * (refreshes the window) and for listings expired within the grace
     * period (relists them). Listings outside the grace window cannot renew.
     */
    public function renew(Property $property, User $owner): Property
    {
        if (! in_array($property->status, ['available', 'unavailable'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only published or recently-expired listings can be renewed.',
            ]);
        }

        if ($property->expires_at && $property->expires_at->lt(now()->subDays($this->graceDays()))) {
            throw ValidationException::withMessages([
                'status' => 'This listing expired more than '.$this->graceDays().' day(s) ago and can no longer be renewed.',
            ]);
        }

        $movingToAvailable = $property->status === 'unavailable';
        if ($movingToAvailable && ! $this->subscriptions->hasQuota($owner, 1)) {
            throw ValidationException::withMessages([
                'status' => $this->config->get('subscriptions.entitlement_over_limit_message', "You've reached your plan's listing limit. Upgrade your subscription to publish more properties."),
            ]);
        }

        $newExpiry = now()->addDays($this->validityDays());

        DB::transaction(function () use ($property, $newExpiry, $movingToAvailable, $owner) {
            $fromStatus = $property->status;

            $property->update([
                'status' => 'available',
                'expires_at' => $newExpiry,
            ]);

            if ($movingToAvailable) {
                PropertyHistory::create([
                    'property_id' => $property->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'available',
                    'changed_by' => $owner->id,
                    'note' => 'Listing renewed',
                ]);
            }
        });

        return $property->fresh(['images', 'owner:id,name,email']);
    }
}