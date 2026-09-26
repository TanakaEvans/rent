<?php

namespace App\Services;

use App\Models\ExpressInterest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewInterestNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Express-Interest queue (S6 — Presentation Release 2.0): the lightweight
 * "mass-enquiry" building block. A tenant taps Express Interest on an
 * available listing instead of composing an enquiry; the owner triages one
 * queue per portfolio. Unlike an enquiry thread there is no back-and-forth —
 * the owner contacts the tenant once (`contacted`), then the row invites or
 * gets archived by either side.
 */
class InterestService
{
    /** Outcome of express(): a new interest row was recorded. */
    public const EXPRESSED_NEW = 'new';

    /** Outcome of express(): a withdrawn/archived interest was re-opened. */
    public const EXPRESSED_REOPENED = 'reopened';

    /** Outcome of express(): the interest was already active (no-op). */
    public const EXPRESSED_ALREADY_ACTIVE = 'already_active';

    /**
     * Record (or re-express) a tenant's interest in an available property.
     *
     * Only `available` listings accept new interest (same contract as
     * enquiries). The unique property×tenant constraint keeps the queue
     * spam-free: a second tap is a no-op while the row is active, and a
     * re-express re-opens an archived row to `interested`.
     *
     * @return string One of the EXPRESSED_* outcomes.
     */
    public function express(User $tenant, Property $property, ?string $note = null): string
    {
        if ($property->status !== 'available') {
            throw new NotFoundHttpException('This property is not accepting interest.');
        }

        $interest = ExpressInterest::where('property_id', $property->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($interest === null) {
            $interest = ExpressInterest::create([
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'note' => $note,
                'status' => 'interested',
            ]);
            $property->owner->notify(new NewInterestNotification($interest));

            return self::EXPRESSED_NEW;
        }

        if ($interest->status === 'archived') {
            $interest->update(['status' => 'interested', 'note' => $note]);
            $property->owner->notify(new NewInterestNotification($interest));

            return self::EXPRESSED_REOPENED;
        }

        return self::EXPRESSED_ALREADY_ACTIVE;
    }

    /**
     * The owner's queue, grouped by property that has interest (newest
     * property first, its interests newest first) and scoped to their own
     * listings only.
     *
     * @param  string|null  $status  Filter active rows to one status.
     */
    public function queueForOwner(User $owner, ?int $propertyId = null, ?string $status = null): Collection
    {
        return Property::where('owner_id', $owner->id)
            ->where('status', '!=', 'draft')
            ->with(['images'])
            ->when($propertyId !== null, fn ($query) => $query->where('id', $propertyId))
            ->whereHas('interests', function ($query) use ($status) {
                if (in_array($status, ExpressInterest::STATUSES, true)) {
                    $query->where('status', $status);
                }
            })
            ->with(['interests' => function ($query) use ($status) {
                $query->with(['tenant:id,name,email,badge_tier,username', 'tenant.tenantProfile'])
                    ->when(in_array($status, ExpressInterest::STATUSES, true), fn ($q) => $q->where('status', $status))
                    ->latest();
            }])
            ->get();
    }

    /**
     * Active-interest breakdown for the owner page header.
     *
     * @return array{interested: int, contacted: int, archived: int}
     */
    public function countsForOwner(User $owner): array
    {
        $rows = ExpressInterest::whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'interested' => (int) $rows->get('interested', 0),
            'contacted' => (int) $rows->get('contacted', 0),
            'archived' => (int) $rows->get('archived', 0),
        ];
    }

    /**
     * Mark an active interest as contacted (the owner reached out). No-op for
     * already-contacted rows; re-open requires the explicit reopen method.
     */
    public function markContacted(User $owner, ExpressInterest $interest): ExpressInterest
    {
        $this->assertOwned($owner, $interest);

        if ($interest->status === 'contacted') {
            return $interest;
        }

        if (! $interest->canTransitionTo('contacted')) {
            throw new ConflictHttpException('This interest has already been resolved.');
        }

        $interest->update(['status' => 'contacted']);

        return $interest;
    }

    /**
     * Pull a contacted interest back into the active queue.
     */
    public function reopen(User $owner, ExpressInterest $interest): ExpressInterest
    {
        $this->assertOwned($owner, $interest);

        if ($interest->status === 'interested') {
            return $interest;
        }

        if (! $interest->canTransitionTo('interested')) {
            throw new ConflictHttpException('This interest cannot be re-opened.');
        }

        $interest->update(['status' => 'interested']);

        return $interest;
    }

    /**
     * Archive an interest so it leaves the active queue (kept for history;
     * a tenant re-express re-opens it).
     */
    public function archive(User $owner, ExpressInterest $interest): ExpressInterest
    {
        $this->assertOwned($owner, $interest);

        if ($interest->status === 'archived') {
            return $interest;
        }

        if (! $interest->canTransitionTo('archived')) {
            throw new ConflictHttpException('This interest cannot be archived.');
        }

        $interest->update(['status' => 'archived']);

        return $interest;
    }

    /**
     * The tenant's own interests, newest first, with their listing.
     */
    public function listForTenant(User $tenant): Collection
    {
        return ExpressInterest::where('tenant_id', $tenant->id)
            ->with(['property:id,title,city,status,price,currency,payment_terms,property_type'])
            ->latest()
            ->get();
    }

    /**
     * A tenant withdraws their own interest (moves it to archived).
     */
    public function withdraw(User $tenant, ExpressInterest $interest): void
    {
        if ($interest->tenant_id !== $tenant->id) {
            throw new NotFoundHttpException('Interest not found.');
        }

        DB::table('express_interests')
            ->where('id', $interest->id)
            ->update(['status' => 'archived', 'updated_at' => now()]);
    }

    /**
     * The viewer's own interest row on a detail page (id + status), or null.
     *
     * @return array{id: int, status: string}|null
     */
    public function stateFor(int $propertyId, ?User $viewer): ?array
    {
        if ($viewer === null) {
            return null;
        }

        $row = ExpressInterest::where('property_id', $propertyId)
            ->where('tenant_id', $viewer->id)
            ->first(['id', 'status']);

        return $row ? ['id' => (int) $row->id, 'status' => $row->status] : null;
    }

    private function assertOwned(User $owner, ExpressInterest $interest): void
    {
        if ($interest->property->owner_id !== $owner->id) {
            throw new NotFoundHttpException('Interest not found.');
        }
    }
}