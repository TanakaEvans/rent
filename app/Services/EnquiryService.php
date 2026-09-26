<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EnquiryRepliedNotification;
use App\Notifications\EnquiryTenantRepliedNotification;
use App\Notifications\NewEnquiryNotification;
use Illuminate\Support\Facades\DB;
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
            ->get(['id', 'owner_id', 'title', 'suburb', 'city', 'status'])
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
            ->with([
                'property:id,title,price,currency,payment_terms,property_type,suburb,city,cover_image,status',
                'messages.sender:id,name',
            ])
            ->latest()
            ->get();
    }

    /**
     * Fetch an enquiry owned by the given owner (row-level isolation),
     * marking it as read when it is still new.
     */
    public function openForOwner(User $owner, int $id): Enquiry
    {
        $enquiry = Enquiry::with([
            'property:id,owner_id,title,address,price,currency,payment_terms,property_type,suburb,city,cover_image,status',
            'tenant:id,name,email',
            'messages.sender:id,name',
        ])->findOrFail($id);

        abort_unless($enquiry->property->owner_id === $owner->id, 404);

        if ($enquiry->status === 'new') {
            $enquiry->update(['status' => 'read', 'read_at' => now()]);
        }

        return $enquiry;
    }

    /**
     * Reply to an enquiry as the property owner. Replies are appended to the
     * thread, never overwritten; the latest owner reply is mirrored onto
     * `reply`/`replied_at` for inbox previews.
     */
    public function reply(User $owner, int $id, string $body): Enquiry
    {
        $enquiry = $this->openForOwner($owner, $id);

        if ($enquiry->status === 'closed') {
            throw ValidationException::withMessages([
                'reply' => ['This enquiry is closed and can no longer be replied to.'],
            ]);
        }

        DB::transaction(function () use ($enquiry, $owner, $body) {
            $enquiry->messages()->create([
                'sender_id' => $owner->id,
                'sender_role' => 'owner',
                'body' => $body,
            ]);

            $enquiry->update([
                'reply' => $body,
                'replied_at' => now(),
                'status' => 'replied',
            ]);
        });

        $enquiry->tenant->notify(new EnquiryRepliedNotification($enquiry));

        return $enquiry;
    }

    /**
     * Follow up on an open enquiry as the tenant who sent it. The thread
     * moves back to `new` so the owner sees an unread message in the inbox.
     */
    public function tenantReply(User $tenant, int $id, string $body): Enquiry
    {
        $enquiry = Enquiry::with('property.owner')->findOrFail($id);

        abort_unless($enquiry->tenant_id === $tenant->id, 404);

        if ($enquiry->status === 'closed') {
            throw ValidationException::withMessages([
                'reply' => ['This enquiry is closed and can no longer be replied to.'],
            ]);
        }

        DB::transaction(function () use ($enquiry, $tenant, $body) {
            $enquiry->messages()->create([
                'sender_id' => $tenant->id,
                'sender_role' => 'tenant',
                'body' => $body,
            ]);

            $enquiry->update(['status' => 'new', 'read_at' => null]);
        });

        $enquiry->property->owner?->notify(new EnquiryTenantRepliedNotification($enquiry, $body));

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