<?php

namespace App\Services;

use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SavedSearchService
{
    /**
     * The tenant's saved searches, newest first.
     */
    public function listFor(User $user)
    {
        return $user->savedSearches()->latest();
    }

    /**
     * Find the tenant's own saved search or 404.
     */
    public function findOwned(int $id, User $user): SavedSearch
    {
        $search = $user->savedSearches()->find($id);

        if (! $search) {
            throw new NotFoundHttpException('Saved search not found.');
        }

        return $search;
    }

    public function create(User $user, string $name, array $criteria, bool $notify): SavedSearch
    {
        return $user->savedSearches()->create([
            'name' => $name,
            'criteria' => array_filter($criteria, fn ($value) => $value !== null && $value !== ''),
            'notify' => $notify,
        ]);
    }

    public function update(User $user, int $id, array $data): SavedSearch
    {
        $search = $this->findOwned($id, $user);

        if (array_key_exists('name', $data)) {
            $search->name = $data['name'];
        }
        if (array_key_exists('criteria', $data)) {
            $search->criteria = array_filter((array) $data['criteria'], fn ($value) => $value !== null && $value !== '');
        }
        if (array_key_exists('notify', $data)) {
            $search->notify = (bool) $data['notify'];
        }
        $search->save();

        return $search->fresh();
    }

    public function delete(User $user, int $id): void
    {
        $this->findOwned($id, $user)->delete();
    }

    /**
     * Does a listed property satisfy a saved-search criteria set? Used both
     * for rendering the "matches" count and by the alerting pass.
     *
     * @param array<string, mixed> $criteria
     */
    public function matches(Property $property, array $criteria): bool
    {
        if (isset($criteria['property_type']) && $property->property_type !== $criteria['property_type']) {
            return false;
        }
        if (isset($criteria['city']) && $property->city !== $criteria['city']) {
            return false;
        }
        if (isset($criteria['suburb']) && $property->suburb !== $criteria['suburb']) {
            return false;
        }
        if (isset($criteria['zone']) && $property->zone !== $criteria['zone']) {
            return false;
        }
        if (isset($criteria['min_price']) && (float) $property->price < (float) $criteria['min_price']) {
            return false;
        }
        if (isset($criteria['max_price']) && (float) $property->price > (float) $criteria['max_price']) {
            return false;
        }
        if (isset($criteria['bedrooms']) && (int) $property->bedrooms < (int) $criteria['bedrooms']) {
            return false;
        }
        if (isset($criteria['bathrooms']) && (int) $property->bathrooms < (int) $criteria['bathrooms']) {
            return false;
        }
        if (array_key_exists('furnished', $criteria) && (bool) $property->furnished !== (bool) $criteria['furnished']) {
            return false;
        }
        if (isset($criteria['verified']) && (bool) $property->verified !== (bool) $criteria['verified']) {
            return false;
        }

        foreach ((array) ($criteria['amenities'] ?? []) as $amenity) {
            if (! in_array($amenity, (array) $property->amenities, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * All saved searches with alerting enabled (for the match pass).
     *
     * @return \Illuminate\Support\Collection<int, SavedSearch>
     */
    public function alertable()
    {
        return SavedSearch::query()
            ->where('notify', true)
            ->with(['user'])
            ->get();
    }
}