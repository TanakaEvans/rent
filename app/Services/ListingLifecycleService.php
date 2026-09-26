<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\User;
use App\Notifications\ListingExpiryReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Listing lifecycle: automatic expiry, expiry reminders and renewal
 * (Marketplace §40/§41). Validity and reminders are rules as data —
 * `listings.validity_days`, `listings.validity_reminders`,
 * `listings.auto_expire` and `listings.renew_grace_days`.
 *
 * Renewal rules (shared by the "Renew listing" action and the status
 * action that moves a listing back to Available):
 *  - A live (available) listing can be renewed at any time; its validity
 *    window restarts from today.
 *  - A listing has "expired" once it is unavailable and its `expires_at`
 *    has passed. Within `listings.renew_grace_days` of expiry it can be
 *    renewed or set back to Available (quota-gated either way).
 *  - After the grace window the listing has "lapsed": neither path can
 *    publish it. Saving the listing through Edit confirms its details are
 *    still accurate and clears the lapsed expiry; it can then be set to
 *    Available as a fresh publish (quota-gated).
 *  - Every new validity window resets the reminder tracker, so each
 *    reminder threshold is sent once per window.
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
     * The attributes that open a fresh validity window from now.
     *
     * @return array{expires_at: Carbon, expiry_reminder_sent_days: null}
     */
    public function freshWindow(): array
    {
        return [
            'expires_at' => now()->addDays($this->validityDays()),
            'expiry_reminder_sent_days' => null,
        ];
    }

    /**
     * Whether the listing is off the marketplace because its window ran out.
     */
    public function isExpired(Property $property): bool
    {
        return $property->status === 'unavailable'
            && $property->expires_at !== null
            && $property->expires_at->isPast();
    }

    /**
     * Whether the listing expired longer ago than the renewal grace window.
     */
    public function isLapsed(Property $property): bool
    {
        return $this->isExpired($property)
            && $property->expires_at->lt(now()->subDays($this->graceDays()));
    }

    /**
     * Why the listing cannot be published again right now, or null when it
     * can (quota aside). Used by both renewal and the status action.
     */
    public function relistBlockReason(Property $property): ?string
    {
        if (! $this->isLapsed($property)) {
            return null;
        }

        return 'This listing expired on '.$property->expires_at->format('d M Y').', more than '.$this->graceDays()
            .' day(s) ago, so it can no longer be renewed. Review and save its details with Edit Property, then set it to Available to publish it again.';
    }

    /**
     * The listing-window facts the owner pages render: the expiry date,
     * whether it has expired or lapsed, and whether "Renew listing" applies.
     *
     * @return array{expires_at: ?string, expired: bool, lapsed: bool, can_renew: bool, renew_until: ?string, grace_days: int}
     */
    public function stateFor(Property $property): array
    {
        $expired = $this->isExpired($property);
        $lapsed = $this->isLapsed($property);

        return [
            'expires_at' => $property->expires_at?->toIso8601String(),
            'expired' => $expired,
            'lapsed' => $lapsed,
            'can_renew' => $property->status === 'available' || ($expired && ! $lapsed),
            'renew_until' => $expired && ! $lapsed
                ? $property->expires_at->copy()->addDays($this->graceDays())->toIso8601String()
                : null,
            'grace_days' => $this->graceDays(),
        ];
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
     * Send each expiry reminder threshold (e.g. 14/7/1 days) once per
     * validity window. A listing only receives the tightest threshold it has
     * reached and not been sent yet, so a missed run never produces a burst
     * of stale reminders. Returns the reminders sent.
     *
     * @param  array<int, int|string>  $thresholds
     * @return array<int, array{property: Property, days: int}>
     */
    public function sendExpiryReminders(array $thresholds): array
    {
        $thresholds = collect($thresholds)
            ->map(fn ($days) => (int) $days)
            ->filter(fn (int $days) => $days > 0)
            ->unique()
            ->sort()
            ->values();

        if ($thresholds->isEmpty()) {
            return [];
        }

        $due = Property::listed()
            ->with('owner:id,name,email')
            ->whereBetween('expires_at', [now(), now()->addDays($thresholds->max())])
            ->get();

        $sent = [];

        foreach ($due as $property) {
            $daysLeft = max(1, (int) ceil(now()->diffInSeconds($property->expires_at) / 86400));
            $threshold = $thresholds->first(fn (int $days) => $daysLeft <= $days);

            if ($threshold === null) {
                continue;
            }

            $alreadySent = $property->expiry_reminder_sent_days;
            if ($alreadySent !== null && $alreadySent <= $threshold) {
                continue;
            }

            $property->update(['expiry_reminder_sent_days' => $threshold]);
            $property->owner?->notify(new ListingExpiryReminderNotification($property, $daysLeft));

            $sent[] = ['property' => $property, 'days' => $daysLeft];
        }

        return $sent;
    }

    /**
     * Renew a listing for another validity period. Works for live listings
     * (refreshes the window) and for listings expired within the grace
     * period (relists them). Lapsed listings cannot renew.
     */
    public function renew(Property $property, User $owner): Property
    {
        if (! in_array($property->status, ['available', 'unavailable'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only published or recently-expired listings can be renewed.',
            ]);
        }

        $blocked = $this->relistBlockReason($property);
        if ($blocked !== null) {
            throw ValidationException::withMessages(['status' => $blocked]);
        }

        $movingToAvailable = $property->status === 'unavailable';
        if ($movingToAvailable && ! $this->subscriptions->hasQuota($owner, 1)) {
            throw ValidationException::withMessages([
                'status' => $this->config->get('subscriptions.entitlement_over_limit_message', "You've reached your plan's listing limit. Upgrade your subscription to publish more properties."),
            ]);
        }

        DB::transaction(function () use ($property, $movingToAvailable, $owner) {
            $fromStatus = $property->status;

            $property->update(['status' => 'available', ...$this->freshWindow()]);

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
