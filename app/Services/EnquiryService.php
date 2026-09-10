<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EnquiryRepliedNotification;
use App\Notifications\NewEnquiryNotification;
use Illuminate\Validation\ValidationException;

class EnquiryService
{
    public function __construct(private readonly AdPlacementService $advertisements)
    {
    }

    /**
     * Create a new enquiry from a tenant for an available property.
     * A tenant can only have one open (non-closed) enquiry per property.
     */
    public function create(User $tenant, Property $property, array $validated): Enquiry
    {
        $open = Enquiry::where('property_id', $property->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', 'closed')
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'message' => ['You already have an open enquiry for this property. The owner will respond shortly.'],
            ]);
        }

        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'message' => $validated['message'],
            'phone' => $validated['phone'] ?? null,
            'status' => 'new',
        ]);

        $this->advertisements->trackForProperty($property, 'enquiry');
        $property->owner->notify(new NewEnquiryNotification($enquiry));

        return $enquiry;
    }

    /**
     * Properties owned by the owner that have at least one enquiry,
     * with their enquiries (newest first), for the grouped inbox.
     */
    public function inboxForOwner(User $owner)
    {
        return Property::where('owner_id', $owner->id)
            ->whereHas('enquiries')
            ->with(['enquiries' => fn ($query) => $query->with('tenant:id,name')->latest()])
            ->get()
            ->map(function (Property $property) {
                $property->latest_enquiry_at = $property->enquiries->max('created_at');

                return $property;
            })
            ->sortByDesc('latest_enquiry_at')
            ->values();
    }

    /**
     * Enquiries sent by a tenant, newest first.
     */
    public function listForTenant(User $tenant)
    {
        return Enquiry::where('tenant_id', $tenant->id)
            ->with('property:id,title,price,property_type,suburb,city,cover_image,status')
            ->latest()
            ->get();
    }

    /**
     * Fetch an enquiry owned by the given owner (row-level isolation),
     * marking it as read when it is still new.
     */
    public function openForOwner(User $owner, int $id): Enquiry
    {
        $enquiry = Enquiry::with(['property:id,owner_id,title,address,price,property_type,suburb,city,cover_image,status', 'tenant:id,name,email'])
            ->findOrFail($id);

        abort_unless($enquiry->property->owner_id === $owner->id, 404);

        if ($enquiry->status === 'new') {
            $enquiry->update(['status' => 'read', 'read_at' => now()]);
        }

        return $enquiry;
    }

    /**
     * Reply to an enquiry as the property owner.
     */
    public function reply(User $owner, int $id, string $body): Enquiry
    {
        $enquiry = $this->openForOwner($owner, $id);

        if ($enquiry->status === 'closed') {
            throw ValidationException::withMessages([
                'reply' => ['This enquiry is closed and can no longer be replied to.'],
            ]);
        }

        $enquiry->update([
            'reply' => $body,
            'replied_at' => now(),
            'status' => 'replied',
        ]);

        $enquiry->tenant->notify(new EnquiryRepliedNotification($enquiry));

        return $enquiry;
    }

    /**
     * Close an enquiry thread as the property owner.
     */
    public function close(User $owner, int $id): Enquiry
    {
        $enquiry = $this->openForOwner($owner, $id);

        if (! $enquiry->canTransitionTo('closed')) {
            throw ValidationException::withMessages([
                'status' => ['This enquiry cannot be closed.'],
            ]);
        }

        $enquiry->update(['status' => 'closed']);

        return $enquiry;
    }
}