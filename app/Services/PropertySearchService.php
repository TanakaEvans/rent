<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertySearchService
{
    public function __construct(private readonly ConfigurationService $config)
    {
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
     * Search available listings with optional combinable filters.
     *
     * Filters (null/empty = ignored):
     *  - q (free text over title/suburb/city/zone/description)
     *  - property_type, city, zone, suburb (string equality)
     *  - min_price, max_price (true range, decimal)
     *  - bedrooms, bathrooms (at-least N semantics)
     *  - furnished (bool), verified (bool)
     *  - availability: now | upcoming | null (any)
     *  - amenities (string[]) — ALL must be present (AND semantics)
     *  - lat, lng, radius_km — optional geo bounds around a point
     *  - sort: newest | recently_updated | price_asc | price_desc |
     *          price_per_m2 | featured
     *  - per_page
     *
     * Featured listings always sort above organic results.
     *
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Property::listed()
            ->with('owner:id,name,email')
            ->with('images')
            ->withCount('views')
            ->withCount('favouritedBy as favourites_count');

        $this->applySuspensionVisibility($query);

        $this->applyFullText($query, $filters['q'] ?? null);
        $this->applyValue($query, 'property_type', $filters['property_type'] ?? null);
        $this->applyValue($query, 'city', $filters['city'] ?? null);
        $this->applyValue($query, 'zone', $filters['zone'] ?? null);
        $this->applyValue($query, 'suburb', $filters['suburb'] ?? null);

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

            return $query
                ->orderBy('featured', 'desc')
                ->orderByRaw($expression)
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        if ($sort === 'featured') {
            return $query
                ->orderBy('featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        [$column, $direction] = match ($sort) {
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'recently_updated' => ['updated_at', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $query
            ->orderBy('featured', 'desc')
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
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

        $matches = Property::listed()
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
        $query = Property::listed()->with(['images', 'owner:id,name,email,verified,created_at']);
        $this->applySuspensionVisibility($query);

        return $query->find($propertyId);
    }

    private function applyFullText($query, ?string $q): void
    {
        if ($q === null || trim($q) === '') {
            return;
        }

        $term = trim($q);
        $query->where(function ($builder) use ($term) {
            $builder->where('title', 'LIKE', "%{$term}%")
                ->orWhere('suburb', 'LIKE', "%{$term}%")
                ->orWhere('city', 'LIKE', "%{$term}%")
                ->orWhere('zone', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
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