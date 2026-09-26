<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PropertyService
{
    /**
     * Attributes a details edit never writes: status only moves through
     * changeStatus()/renewal, media only through syncMedia().
     */
    private const NON_EDITABLE = ['owner_id', 'status', 'expires_at', 'expiry_reminder_sent_days', 'cover', 'cover_image', 'images'];

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
            ->withCount('applications')
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
     * When media is submitted (the listing form always does), at least one
     * photo (cover or gallery) is required.
     *
     * @param  array<string, mixed>  $data
     * @param  array{cover?: ?UploadedFile, images?: array<int, string|UploadedFile>}  $media
     */
    public function create(array $data, User $owner, array $media = []): Property
    {
        $status = $data['status'] ?? 'unavailable';

        if ($status === 'available' && ! $this->subscriptions->hasQuota($owner, 1)) {
            throw $this->quotaError();
        }

        $attributes = array_diff_key($data, array_flip(self::NON_EDITABLE));
        $attributes['owner_id'] = $owner->id;
        $attributes['status'] = $status;

        if ($status === 'available') {
            $attributes = [...$attributes, ...$this->lifecycle->freshWindow()];
        }

        $property = DB::transaction(function () use ($attributes, $media) {
            $property = Property::create($attributes);

            if ($media !== []) {
                $this->syncMedia($property, [
                    'cover' => $media['cover'] ?? null,
                    'cover_image' => null,
                    'images' => $media['images'] ?? [],
                ]);
            }

            return $property;
        });

        $this->alerts->notifyNewMatches($property->fresh());

        return $property;
    }

    /**
     * Update an owned property's details and return the refreshed model.
     * Status is never changed here (see changeStatus()). Saving the details
     * of a lapsed listing clears its lapsed expiry so it can be published
     * again (see ListingLifecycleService). A rent reduction fires price-drop
     * alerts to tenants who saved the property.
     *
     * @param  array<string, mixed>  $data
     * @param  array{cover?: ?UploadedFile, cover_image?: ?string, images?: array<int, string|UploadedFile>}  $media
     */
    public function update(int $propertyId, array $data, User $owner, array $media = []): Property
    {
        $property = $this->findOwned($propertyId, $owner);
        $oldPrice = (float) $property->price;

        $attributes = array_diff_key($data, array_flip(self::NON_EDITABLE));

        if ($this->lifecycle->isLapsed($property)) {
            $attributes['expires_at'] = null;
            $attributes['expiry_reminder_sent_days'] = null;
        }

        DB::transaction(function () use ($property, $attributes, $media) {
            $property->update($attributes);
            $this->syncMedia($property, $media);
        });

        if (array_key_exists('price', $attributes) && (float) $attributes['price'] !== $oldPrice) {
            $this->alerts->notifyPriceDrop($property->fresh(), $oldPrice, (float) $attributes['price']);
        }

        return $property->fresh(['images', 'owner:id,name,email']);
    }

    /**
     * Persist the listing photos.
     *
     *  - `images` present: the submitted gallery is the source of truth, in
     *    order. String entries keep existing photos of this property (paths
     *    that do not belong to it are ignored); uploads are stored.
     *  - `cover_image` present: the retained cover path (null = removed).
     *  - `cover` upload: replaces the cover.
     *  - Keys absent: that part of the media is left untouched.
     *
     * The cover falls back to the first gallery photo. The listing must keep
     * at least one photo. Only files this service wrote (`/storage/properties/`)
     * are deleted once nothing references them — seeded artwork never is.
     *
     * @param  array{cover?: ?UploadedFile, cover_image?: ?string, images?: array<int, string|UploadedFile>}  $media
     */
    public function syncMedia(Property $property, array $media): void
    {
        $coverUpload = ($media['cover'] ?? null) instanceof UploadedFile ? $media['cover'] : null;
        $galleryProvided = array_key_exists('images', $media);
        $coverProvided = array_key_exists('cover_image', $media);

        if (! $galleryProvided && ! $coverProvided && $coverUpload === null) {
            return;
        }

        $currentGallery = $property->images()->pluck('path')->all();
        $currentCover = $property->cover_image;
        $owned = array_values(array_filter([...$currentGallery, $currentCover]));

        $entries = [];
        foreach ($galleryProvided ? (array) ($media['images'] ?? []) : $currentGallery as $entry) {
            if ($entry instanceof UploadedFile) {
                $entries[] = $entry;
            } elseif (is_string($entry) && in_array(trim($entry), $owned, true)) {
                $entries[] = trim($entry);
            }
        }

        $retainedCover = $currentCover;
        if ($coverProvided) {
            $candidate = is_string($media['cover_image']) ? trim($media['cover_image']) : '';
            $retainedCover = in_array($candidate, $owned, true) ? $candidate : null;
        }

        if ($coverUpload === null && $retainedCover === null && $entries === []) {
            throw ValidationException::withMessages([
                'media' => 'Keep at least one photo — a cover image or a gallery photo.',
            ]);
        }

        $disk = Storage::disk('public');
        $folder = "properties/{$property->id}";

        $gallery = [];
        foreach ($entries as $entry) {
            $gallery[] = $entry instanceof UploadedFile ? $this->store($entry, $folder, 'photo') : $entry;
        }
        $gallery = array_values(array_unique($gallery));

        $cover = $coverUpload ? $this->store($coverUpload, $folder, 'cover') : ($retainedCover ?? $gallery[0]);

        if ($galleryProvided) {
            PropertyImage::where('property_id', $property->id)->delete();

            foreach ($gallery as $sort => $path) {
                PropertyImage::create([
                    'property_id' => $property->id,
                    'path' => $path,
                    'caption' => $property->title,
                    'sort_order' => $sort,
                ]);
            }
        }

        $property->update(['cover_image' => $cover]);

        $stillUsed = [...$gallery, $cover, ...($galleryProvided ? [] : $currentGallery)];
        foreach (array_unique($owned) as $old) {
            if (! in_array($old, $stillUsed, true) && str_starts_with($old, '/storage/properties/')) {
                $disk->delete($this->publicPathToStored($old));
            }
        }
    }

    private function store(UploadedFile $file, string $folder, string $prefix): string
    {
        $name = $prefix.'-'.now()->format('His').'-'.Str::random(6).'.'.$file->getClientOriginalExtension();

        return $this->storedToPublicPath(Storage::disk('public')->putFileAs($folder, $file, $name));
    }

    private function storedToPublicPath(string $stored): string
    {
        return '/storage/'.ltrim($stored, '/');
    }

    private function publicPathToStored(string $public): string
    {
        return ltrim(str_replace('/storage/', '', $public), '/');
    }

    /**
     * Delete an owned property. A property with lease or payment records is
     * kept for the record — the owner takes it off the market instead.
     */
    public function delete(int $propertyId, User $owner): bool
    {
        $property = $this->findOwned($propertyId, $owner);

        $blocked = $this->deleteBlockReason($property);
        if ($blocked !== null) {
            throw ValidationException::withMessages(['property' => $blocked]);
        }

        DB::transaction(function () use ($property) {
            $property->delete();
        });

        return true;
    }

    /**
     * Why the property cannot be deleted (it has any lease, in any state, or
     * rent payments on record), or null when it can.
     */
    public function deleteBlockReason(Property $property): ?string
    {
        $hasRecords = $property->leases()->exists()
            || Payment::whereHas('invoice', fn ($query) => $query->where('property_id', $property->id))->exists();

        return $hasRecords
            ? 'This property has lease or rent payment records, so it cannot be deleted. Set it to Unavailable to take it off the marketplace instead.'
            : null;
    }

    /**
     * Move a property to a new status through the explicit state machine.
     * Moving to Available is quota-gated, follows the listing renewal rules
     * (ListingLifecycleService) and opens a fresh validity window. Every
     * successful transition writes a history row.
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

        if ($newStatus === 'available') {
            $blocked = $this->lifecycle->relistBlockReason($property);
            if ($blocked !== null) {
                throw ValidationException::withMessages(['status' => $blocked]);
            }
        }

        DB::transaction(function () use ($property, $newStatus, $owner) {
            $fromStatus = $property->status;

            $property->update([
                'status' => $newStatus,
                ...($newStatus === 'available' ? $this->lifecycle->freshWindow() : []),
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
