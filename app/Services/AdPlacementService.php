<?php

namespace App\Services;

use App\Models\AdEvent;
use App\Models\AdPackage;
use App\Models\AdPlacement;
use App\Models\Property;
use App\Models\User;
use App\Notifications\AdPlacementActivatedNotification;
use App\Notifications\AdPlacementCancelledNotification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Featured & advertising engine (Module 13, Wave 4 slice 6).
 *
 * All commercial values come from the `featured.*` configuration plus the
 * `ad_packages` catalogue — no price/duration numbers live in code. Money is
 * stored exact to the cent and every proration is integer math on cents.
 */
class AdPlacementService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Book a placement. Typically creates a `reserved` order that staff
     * approve (payment/approval gateway); when `featured.approval_required`
     * is off a booking activates its window immediately.
     */
    public function book(User $owner, Property $property, AdPackage $package): AdPlacement
    {
        if (! (bool) $this->config->get('featured.enabled', true)) {
            throw ValidationException::withMessages([
                'package_id' => ['Promoted listings are currently unavailable.'],
            ]);
        }

        if ($property->owner_id !== $owner->id) {
            throw ValidationException::withMessages([
                'property_id' => ['You can only promote your own listings.'],
            ]);
        }

        if ($property->status !== 'available') {
            throw ValidationException::withMessages([
                'property_id' => ['Only an available listing can be promoted.'],
            ]);
        }

        $perPropertyMax = (int) $this->config->get('featured.max_per_property', 1);
        if ($perPropertyMax < 1 || $this->openCountForProperty($property->id) >= $perPropertyMax) {
            throw ValidationException::withMessages([
                'property_id' => ['This property already holds the maximum number of placements.'],
            ]);
        }

        $perOwnerMax = (int) $this->config->get('featured.max_active_per_owner', 3);
        if ($this->openCountForOwner($owner->id) >= $perOwnerMax) {
            throw ValidationException::withMessages([
                'package_id' => ['Your promotion budget is used up. Cancel a placement to book another.'],
            ]);
        }

        $amount = (float) $package->price > 0
            ? $package->price
            : (string) $this->config->get('featured.default_price', '0.00');

        $placement = AdPlacement::create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'package_id' => $package->id,
            'amount' => $amount,
            'status' => 'reserved',
            'credit_amount' => '0.00',
        ]);

        if (! (bool) $this->config->get('featured.approval_required', true)) {
            return $this->approve($placement);
        }

        return $placement;
    }

    /**
     * Staff approve a reserved order: settle (paid_at) and open the window.
     * The window is now() .. now()+duration from the package, falling back to
     * `featured.auto_expiry_days`, and the property becomes featured.
     */
    public function approve(AdPlacement $placement): AdPlacement
    {
        $this->assertStatus($placement, ['reserved'], 'Only reserved placements can be approved.');

        $duration = (int) $placement->package?->duration_days
            ?: (int) $this->config->get('featured.auto_expiry_days', 30);
        $window = now()->copy()->addDays(max(1, $duration));

        $placement->update([
            'starts_at' => now(),
            'ends_at' => $window,
            'paid_at' => now(),
            'status' => 'active',
        ]);

        $placement->property->update(['featured' => true]);
        $placement->owner->notify(new AdPlacementActivatedNotification($placement->fresh(['property'])));

        return $placement->fresh();
    }

    /**
     * Admin cancels a placement. Reserved orders credit fully; active/paused
     * windows are prorated down to the unused portion (integer cents) and the
     * featured flag flips off unless another placement keeps it promoted.
     */
    public function cancel(AdPlacement $placement, ?string $note = null): AdPlacement
    {
        $this->assertStatus($placement, ['reserved', 'active', 'paused'], 'This placement can no longer be cancelled.');

        $credit = in_array($placement->status, ['reserved'], true)
            ? $this->cents($placement->amount)
            : $this->proratedCredit($placement);

        $placement->update([
            'status' => 'cancelled',
            'credit_amount' => $this->fromCents($credit),
            'admin_note' => $note,
        ]);

        $this->syncFeatured($placement->property_id);
        $placement->owner->notify(new AdPlacementCancelledNotification($placement->fresh(['property'])));

        return $placement->fresh();
    }

    /**
     * Moderation (Module 19): pause freezes the window and drops promotion.
     */
    public function pause(AdPlacement $placement, ?string $note = null): AdPlacement
    {
        $this->assertStatus($placement, ['active'], 'Only an active placement can be paused.');

        $placement->update([
            'status' => 'paused',
            'paused_at' => now(),
            'admin_note' => $note,
        ]);

        $this->syncFeatured($placement->property_id);

        return $placement->fresh();
    }

    /**
     * Resume after a pause: the frozen span is added back to the window.
     */
    public function resume(AdPlacement $placement, ?string $note = null): AdPlacement
    {
        $this->assertStatus($placement, ['paused'], 'Only a paused placement can be resumed.');

        $frozen = now()->diffInSeconds($placement->paused_at);

        $placement->update([
            'status' => 'active',
            'ends_at' => $placement->ends_at->copy()->addSeconds($frozen),
            'paused_at' => null,
            'admin_note' => $note,
        ]);

        $placement->property->update(['featured' => true]);

        return $placement->fresh();
    }

    /**
     * Cron sweep (NFR-01). Expire every active window whose end has passed;
     * paused placements are frozen and skipped. Returns the count expired.
     */
    public function expireDue(): int
    {
        $due = AdPlacement::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($due as $placement) {
            $placement->update(['status' => 'expired']);
            $this->syncFeatured($placement->property_id);
        }

        return $due->count();
    }

    /**
     * Record a marketplace event against a property's active placement,
     * if one exists (silent no-op otherwise — NFR-03, no personal data).
     */
    public function trackForProperty(Property $property, string $eventType): void
    {
        $placement = $this->activeForProperty($property->id);
        if (! $placement) {
            return;
        }

        AdEvent::create([
            'placement_id' => $placement->id,
            'event_type' => $eventType,
        ]);
    }

    /**
     * Record an impression for every active placement displayed on the page.
     */
    public function recordImpressionsFor(Collection $properties): void
    {
        $ids = $properties->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $placements = AdPlacement::query()
            ->where('status', 'active')
            ->whereIn('property_id', $ids)
            ->get(['id']);

        if ($placements->isEmpty()) {
            return;
        }

        $rows = $placements->map(fn (AdPlacement $placement) => [
            'placement_id' => $placement->id,
            'event_type' => 'impression',
            'created_at' => now(),
        ])->all();

        AdEvent::insert($rows);
    }

    /**
     * Performance stats per placement over its window (FR-05).
     */
    public function statsFor(AdPlacement $placement): array
    {
        $counts = $placement->events()
            ->select('event_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return [
            'impressions' => (int) ($counts['impression'] ?? 0),
            'clicks' => (int) ($counts['click'] ?? 0),
            'enquiries' => (int) ($counts['enquiry'] ?? 0),
            'applications' => (int) ($counts['application'] ?? 0),
        ];
    }

    /**
     * Owner advertising page payload: pricing active, my placements with
     * stats, and the bookable properties with their promotion state.
     */
    public function ownerIndex(User $owner): array
    {
        $openIds = AdPlacement::forOwner($owner->id)->open()->pluck('property_id')->all();

        $packages = AdPackage::active()->orderBy('price')->get();

        $placements = AdPlacement::forOwner($owner->id)
            ->with(['property:id,title,suburb,city,cover_image,status,featured', 'package:id,name,placement_type'])
            ->latest()
            ->get()
            ->map(function (AdPlacement $placement) {
                $placement->stats = $this->statsFor($placement);

                return $placement;
            });

        $properties = Property::listed()
            ->where('owner_id', $owner->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Property $property) use ($openIds) {
                $property->promotion_open = in_array($property->id, $openIds, true);

                return $property;
            });

        return [
            'enabled' => (bool) $this->config->get('featured.enabled', true),
            'approval_required' => (bool) $this->config->get('featured.approval_required', true),
            'packages' => $packages,
            'placements' => $placements,
            'properties' => $properties,
        ];
    }

    /**
     * Admin moderation payload: pending approvals, live placements and history.
     */
    public function adminIndex(): array
    {
        $with = ['property:id,title,suburb,city,cover_image,status', 'owner:id,name,email', 'package:id,name,placement_type'];

        return [
            'pending' => AdPlacement::where('status', 'reserved')->with($with)->latest()->get(),
            'live' => AdPlacement::whereIn('status', ['active', 'paused'])->with($with)->latest()->get()
                ->each(fn (AdPlacement $placement) => $placement->stats = $this->statsFor($placement)),
            'history' => AdPlacement::whereIn('status', ['expired', 'cancelled'])->with($with)->latest()->take(20)->get(),
        ];
    }

    public function activeForProperty(int $propertyId): ?AdPlacement
    {
        return AdPlacement::query()
            ->where('property_id', $propertyId)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    public function openCountForProperty(int $propertyId): int
    {
        return AdPlacement::where('property_id', $propertyId)->open()->count();
    }

    public function openCountForOwner(int $ownerId): int
    {
        return AdPlacement::forOwner($ownerId)->open()->count();
    }

    private function assertStatus(AdPlacement $placement, array $allowed, string $message): void
    {
        if (! in_array($placement->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [$message],
            ]);
        }
    }

    /**
     * Unused portion of an active/paused window as cents (integer math).
     * The window is wall-clock: total booked span vs the moment of cancel.
     */
    private function proratedCredit(AdPlacement $placement): int
    {
        if (! $placement->starts_at || ! $placement->ends_at) {
            return 0;
        }

        $total = $placement->starts_at->diffInSeconds($placement->ends_at);
        if ($total < 1) {
            return 0;
        }

        $elapsed = min(max(0, $placement->starts_at->diffInSeconds(now())), $total);
        $remaining = $total - $elapsed;

        return intdiv($this->cents($placement->amount) * $remaining, $total);
    }

    /**
     * Recompute a property's featured flag from its active placements, so the
     * flag always reflects a live promotion window.
     */
    private function syncFeatured(int $propertyId): void
    {
        $promoted = AdPlacement::where('property_id', $propertyId)
            ->where('status', 'active')
            ->exists();

        Property::where('id', $propertyId)->update(['featured' => $promoted]);
    }

    private function cents(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}