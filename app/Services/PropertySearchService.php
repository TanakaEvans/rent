<?php

namespace App\Services;

use App\Models\ExpressInterest;
use App\Models\Property;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertySearchService
{
    public function __construct(
        private readonly ConfigurationService $config,
        private readonly LocationVisibilityService $location,
    ) {
    }

    /**
     * The base query for every public marketplace surface (search results,
     * featured, just listed, filter options, explore, recommendations,
     * suggestions, detail page): `available` listings, minus those hidden by
     * the configured suspension behaviour.
     */
    public function publicListings(): Builder
    {
        $query = Property::listed();
        $this->applySuspensionVisibility($query);

        return $query;
    }

    /**
     * Apply the configured suspension behaviour to the listing query:
     * `hide_listings` removes owned `available` properties whose owner is
     * currently suspended from the public marketplace.
     */
    private function applySuspensionVisibility($query): void
    {
        if ($this->config->get('subscriptions.suspension.behaviour', 'keep_listings') === 'hide_listings') {
            $query->whereDoesntHave('owner.subscriptions', fn ($builder) => $builder->where('status', 'suspended'));
        }
    }

    /**
     * Split free text into lower-case keyword tokens (letters/digits only,
     * so tokens are safe inside a LIKE pattern). Single characters are
     * dropped as noise.
     *
     * @return string[]
     */
    public static function keywordTokens(?string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim((string) $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn ($token) => mb_strlen($token) > 1)));
    }

    /**
     * Search available listings with optional combinable filters.
     *
     * Filters (null/empty = ignored):
     *  - q (free text over title/suburb/city/zone/description; every
     *    keyword token must match one of those columns)
     *  - keywords (the natural-language leftover of q; used instead of q
     *    when present)
     *  - property_type, city, zone, suburb (string equality)
     *  - min_price, max_price (true range, decimal)
     *  - bedrooms, bathrooms (at-least N semantics)
     *  - furnished (bool), verified (bool)
     *  - payment_terms, security_type, parking_type, preferred_tenant
     *    (S1-taxonomy option filters)
     *  - availability: now | upcoming | null (any)
     *  - amenities (string[]) — ALL must be present (AND semantics)
     *  - lat, lng, radius_km — optional geo bounds around a point
     *  - sort: newest | recently_updated | price_asc | price_desc |
     *          price_per_m2 | featured | top_rated
     *  - per_page
     *
     * Featured listings always sort above organic results.
     *
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = $this->publicListings()
            ->with('owner:id,name,email,verified,badge_tier')
            ->with('images');

        $this->applyFullText($query, array_key_exists('keywords', $filters) ? $filters['keywords'] : ($filters['q'] ?? null));
        $this->applyValue($query, 'property_type', $filters['property_type'] ?? null);
        $this->applyValue($query, 'city', $filters['city'] ?? null);
        $this->applyValue($query, 'zone', $filters['zone'] ?? null);
        $this->applyValue($query, 'suburb', $filters['suburb'] ?? null);
        $this->applyValue($query, 'payment_terms', $filters['payment_terms'] ?? null);
        $this->applyValue($query, 'security_type', $filters['security_type'] ?? null);
        $this->applyValue($query, 'parking_type', $filters['parking_type'] ?? null);
        $this->applyValue($query, 'preferred_tenant', $filters['preferred_tenant'] ?? null);

        $this->applyMinimum($query, 'price', $filters['min_price'] ?? null);
        $this->applyMaximum($query, 'price', $filters['max_price'] ?? null);
        $this->applyMinimum($query, 'bedrooms', $filters['bedrooms'] ?? null);
        $this->applyMinimum($query, 'bathrooms', $filters['bathrooms'] ?? null);

        if (($filters['furnished'] ?? null) !== null) {
            $query->where('furnished', (bool) $filters['furnished']);
        }

        if (($filters['verified'] ?? null) !== null) {
            $query->where('verified', (bool) $filters['verified']);
        }

        $this->applyAvailability($query, $filters['availability'] ?? null);
        $this->applyAmenities($query, $filters['amenities'] ?? null);
        $this->applyRadius($query, $filters);

        $sort = $filters['sort'] ?? 'newest';
        $perPage = $this->perPage($filters);

        if ($sort === 'price_per_m2') {
            $direction = ($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $expression = 'CASE WHEN building_size > 0 THEN price / building_size ELSE 1000000 END '.$direction;

            return $this->withCounts($query
                ->orderBy('featured', 'desc')
                ->orderByRaw($expression)
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString());
        }

        if ($sort === 'featured') {
            return $this->withCounts($query
                ->orderBy('featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString());
        }

        if ($sort === 'top_rated') {
            return $this->withCounts($query
                ->orderBy('featured', 'desc')
                ->orderByRaw('(SELECT CASE WHEN u.ratings_count > 0 THEN 0 ELSE 1 END FROM auth_users u WHERE u.id = properties.owner_id)')
                ->orderByRaw('(SELECT u.rating_avg FROM auth_users u WHERE u.id = properties.owner_id) DESC')
                ->orderBy('created_at', 'desc')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString());
        }

        [$column, $direction] = match ($sort) {
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'recently_updated' => ['updated_at', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $this->withCounts($query
            ->orderBy('featured', 'desc')
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString());
    }

    /**
     * View and favourite counts for the listings on this page only (as
     * select subqueries they would run for every matching listing).
     */
    private function withCounts(LengthAwarePaginator $page): LengthAwarePaginator
    {
        $page->getCollection()->loadCount(['views', 'favouritedBy as favourites_count']);

        // The public marketplace map never exposes exact coordinates: every
        // result is masked to its stable approximate point (inDrive-style).
        $radius = (int) $this->config->get('privacy.location.approx_radius_m', 500);
        $page->getCollection()->each(fn (Property $property) => $this->location->mask($property, false, $radius));

        return $page;
    }

    private function perPage(array $filters): int
    {
        return isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : $this->config->get('marketplace.listings_per_page', 12);
    }

    /**
     * Autocomplete suggestions for the hero search bar: matching suburbs,
     * cities, zones (jump into a search) and property titles (jump to the
     * detail page).
     *
     * @return array<int, array{type: string, label: string, suburb: ?string, city: ?string, id: ?int}>
     */
    public function suggestions(?string $term, int $limit = 6): array
    {
        $limit = max(1, min(10, $limit));
        $term = trim((string) $term);

        if ($term === '') {
            return [];
        }

        $matches = $this->publicListings()
            ->where(function ($query) use ($term) {
                $query->where('title', 'LIKE', "%{$term}%")
                    ->orWhere('suburb', 'LIKE', "%{$term}%")
                    ->orWhere('city', 'LIKE', "%{$term}%")
                    ->orWhere('zone', 'LIKE', "%{$term}%");
            })
            ->get(['id', 'title', 'suburb', 'city', 'zone']);

        $parts = [];

        foreach ($matches as $property) {
            foreach ([
                ['type' => 'suburb', 'label' => $property->suburb, 'city' => $property->city],
                ['type' => 'city', 'label' => $property->city, 'city' => $property->city],
                ['type' => 'zone', 'label' => $property->zone, 'city' => null],
            ] as $candidate) {
                if (! $candidate['label'] || stripos($candidate['label'], $term) === false) {
                    continue;
                }
                $key = $candidate['type'].':'.$candidate['label'];
                if (isset($parts[$key])) {
                    continue;
                }
                $parts[$key] = [
                    'type' => $candidate['type'],
                    'label' => $candidate['label'],
                    'city' => $candidate['city'],
                    'id' => null,
                ];
            }
        }

        foreach ($matches as $property) {
            if (stripos($property->title, $term) !== false) {
                $parts['title:'.$property->id] = [
                    'type' => 'title',
                    'label' => $property->title,
                    'city' => $property->city,
                    'id' => $property->id,
                ];
            }
        }

        return array_slice(array_values($parts), 0, $limit);
    }

    /**
     * Load a single publicly-viewable (available) property for the detail page.
     */
    public function findPublicDetail(int $propertyId): ?Property
    {
        return $this->publicListings()
            ->with(['images', 'owner:id,name,email,verified,badge_tier,created_at'])
            ->find($propertyId);
    }

    /**
     * Load an off-market property (reserved, occupied, hidden…) for a viewer
     * with a legitimate relationship to it: its owner, an admin, or a user
     * who leased, applied for, enquired about, expressed interest in,
     * favourited or reported it. Everyone else gets null (404).
     */
    public function findRelatedDetail(int $propertyId, User $viewer): ?Property
    {
        $property = Property::with(['images', 'owner:id,name,email,verified,badge_tier,created_at'])->find($propertyId);

        if (! $property) {
            return null;
        }

        if ((int) $property->owner_id === (int) $viewer->id ||$viewer->hasAnyRole(['Admin', 'Superuser'])) {
            return $property;
        }

        $related = $property->leases()->where('tenant_id', $viewer->id)->exists()
            || $property->applications()->where('applicant_id', $viewer->id)->exists()
            || $property->enquiries()->where('tenant_id', $viewer->id)->exists()
            || ExpressInterest::where('property_id', $property->id)->where('tenant_id', $viewer->id)->exists()
            || $property->favouritedBy()->whereKey($viewer->id)->exists()
            || Report::where('subject_type', 'property')
                ->where('subject_id', $property->id)
                ->where('reporter_id', $viewer->id)
                ->exists();

        return $related ? $property : null;
    }

    /**
     * Token-based keyword match: every token must appear in at least one of
     * title/suburb/city/zone/description (tokens may match different
     * columns), so "Borrowdale, Harare" or "garden cottage" still match.
     */
    private function applyFullText($query, ?string $q): void
    {
        foreach (self::keywordTokens($q) as $token) {
            $query->where(function ($builder) use ($token) {
                $builder->where('title', 'LIKE', "%{$token}%")
                    ->orWhere('suburb', 'LIKE', "%{$token}%")
                    ->orWhere('city', 'LIKE', "%{$token}%")
                    ->orWhere('zone', 'LIKE', "%{$token}%")
                    ->orWhere('description', 'LIKE', "%{$token}%");
            });
        }
    }

    /**
     * Availability: `now` = ready to move in today; `upcoming` = available
     * from a future date; null = either.
     */
    private function applyAvailability($query, ?string $availability): void
    {
        if ($availability === 'now') {
            $query->where(function ($builder) {
                $builder->whereNull('available_from')
                    ->orWhereDate('available_from', '<=', now()->toDateString());
            });
        } elseif ($availability === 'upcoming') {
            $query->whereDate('available_from', '>', now()->toDateString());
        }
    }

    /**
     * Every amenity in the list must be present (AND semantics). The JSON
     * column is matched at the serialized level, which is portable across
     * MySQL and SQLite.
     *
     * @param string[]|null $amenities
     */
    private function applyAmenities($query, ?array $amenities): void
    {
        $amenities = array_values(array_filter((array) $amenities));

        foreach ($amenities as $amenity) {
            $query->where('amenities', 'LIKE', '%"'.trim($amenity).'"%');
        }
    }

    /**
     * Optional geo bounding by lat/lng + radius (km). Purely a coarse
     * filter on the decimal coords; exact map pop-ups come from the client.
     */
    private function applyRadius($query, array $filters): void
    {
        $lat = $filters['lat'] ?? null;
        $lng = $filters['lng'] ?? null;
        $radius = $filters['radius_km'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng) || ! is_numeric($radius) || (float) $radius <= 0) {
            return;
        }

        $latitude = (float) $lat;
        $longitude = (float) $lng;
        $radius = (float) $radius;

        // 1 degree of latitude ~= 111km. A square bound is a good cheap
        // approximation for the filter; ordering by exact distance is done
        // client-side on the map.
        $deltaLat = $radius / 111;
        $deltaLng = $radius / (111 * abs(cos(deg2rad($latitude))) > 0.01 ? abs(cos(deg2rad($latitude))) : 1);

        $query->whereBetween('latitude', [$latitude - $deltaLat, $latitude + $deltaLat])
            ->whereBetween('longitude', [$longitude - $deltaLng, $longitude + $deltaLng]);
    }

    private function applyValue($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, $value);
        }
    }

    private function applyMinimum($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, '>=', (float) $value);
        }
    }

    private function applyMaximum($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, '<=', (float) $value);
        }
    }
}