<?php

namespace App\Services;

use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use App\Notifications\ApplicationRejectedNotification;
use App\Notifications\NewApplicationNotification;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    /**
     * Statuses that count as an active (open) application for deduplication.
     */
    public const ACTIVE = ['pending', 'shortlisted', 'approved'];

    /**
     * Submit an application from a tenant for an available property.
     * A tenant may hold only one active application per property.
     */
    public function create(User $tenant, Property $property, ?string $message): RentalApplication
    {
        if (RentalApplication::where('property_id', $property->id)
            ->where('applicant_id', $tenant->id)
            ->whereIn('status', self::ACTIVE)
            ->exists()) {
            throw ValidationException::withMessages([
                'message' => ['You already have an active application for this property.'],
            ]);
        }

        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $tenant->id,
            'message' => $message,
            'status' => 'pending',
        ]);

        $property->owner->notify(new NewApplicationNotification($application));

        return $application;
    }

    /**
     * Applications submitted by a tenant, newest first.
     */
    public function listForTenant(User $tenant)
    {
        return RentalApplication::where('applicant_id', $tenant->id)
            ->with('property:id,title,price,property_type,suburb,city,cover_image,status')
            ->latest()
            ->get();
    }

    /**
     * Properties owned by the owner that have at least one application,
     * with their applications (newest first) for the grouped review screen.
     */
    public function listForOwner(User $owner)
    {
        return Property::where('owner_id', $owner->id)
            ->whereHas('applications')
            ->withCount('leases')
            ->with(['applications' => fn ($query) => $query->with('applicant:id,name,email')->latest()])
            ->get()
            ->map(function (Property $property) {
                $property->latest_application_at = $property->applications->max('created_at');

                return $property;
            })
            ->sortByDesc('latest_application_at')
            ->values();
    }

    /**
     * Fetch an application against one of the owner's properties
     * (row-level isolation, 404 on foreign rows).
     */
    public function findOwnedByOwner(User $owner, int $id): RentalApplication
    {
        $application = RentalApplication::with(['property:id,title,owner_id', 'applicant:id,name,email'])
            ->findOrFail($id);

        abort_unless($application->property->owner_id === $owner->id, 404);

        return $application;
    }

    /**
     * Move a reviewable application through the transition guard.
     */
    private function assertTransition(RentalApplication $application, string $target): void
    {
        if (! $application->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ['This application can no longer be changed.'],
            ]);
        }
    }

    /**
     * Approve an application. Approval stays inert (property status untouched)
     * until the lease slice picks up the handover.
     * Exclusivity is enforced here: only one approved application per property.
     */
    public function approve(User $owner, int $id): RentalApplication
    {
        $application = $this->findOwnedByOwner($owner, $id);
        $this->assertTransition($application, 'approved');

        $exists = RentalApplication::where('property_id', $application->property_id)
            ->where('id', '!=', $application->id)
            ->where('status', 'approved')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => ['Another applicant is already approved for this property.'],
            ]);
        }

        $application->update([
            'status' => 'approved',
            'reviewed_by' => $owner->id,
        ]);

        $application->applicant->notify(new ApplicationApprovedNotification($application));

        return $application;
    }

    /**
     * Toggle an application between pending and shortlisted.
     */
    public function toggleShortlist(User $owner, int $id): RentalApplication
    {
        $application = $this->findOwnedByOwner($owner, $id);

        $target = $application->status === 'shortlisted' ? 'pending' : 'shortlisted';
        $this->assertTransition($application, $target);

        $application->update([
            'status' => $target,
            'reviewed_by' => $owner->id,
        ]);

        return $application;
    }

    /**
     * Reject an application, recording the required reason and reviewer.
     */
    public function reject(User $owner, int $id, string $reason): RentalApplication
    {
        $application = $this->findOwnedByOwner($owner, $id);
        $this->assertTransition($application, 'rejected');

        $application->update([
            'status' => 'rejected',
            'reject_reason' => $reason,
            'reviewed_by' => $owner->id,
        ]);

        $application->applicant->notify(new ApplicationRejectedNotification($application));

        return $application;
    }
}