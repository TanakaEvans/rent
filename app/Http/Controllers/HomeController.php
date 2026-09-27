<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Property;
use App\Services\AdPlacementService;
use App\Services\ConfigurationService;
use App\Services\NaturalLanguageSearchService;
use App\Services\PropertySearchService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        private readonly PropertySearchService $search,
        private readonly NaturalLanguageSearchService $naturalLanguage,
        private readonly ConfigurationService $config,
        private readonly RecommendationService $recommendations,
        private readonly AdPlacementService $advertisements,
    ) {
    }

    /**
     * Show the public marketplace landing page.
     */
    public function index(Request $request)
    {
        $filters = $this->normalizeFilters($request);

        if (($filters['q'] ?? null) !== null) {
            $filters = array_merge($filters, $this->naturalLanguage->parse($filters['q']));
        }

        $paginator = $this->search->search($filters);

        $viewer = $request->user();
        $isTenant = $viewer?->hasRole('Tenant');

        $featured = $this->search->publicListings()
            ->with(['owner:id,name,email,verified,badge_tier', 'images'])
            ->featured()
            ->latest()
            ->take(3)
            ->get()
            ->loadCount(['views', 'favouritedBy as favourites_count']);

        // Record one impression for every promotion window shown in the
        // featured rail (FR-05/NFR-03 — placement-level, no personal data).
        $this->advertisements->recordImpressionsFor($featured);

        return Inertia::render('Marketplace/Index', [
            'properties' => $paginator->items(),
            'total' => $paginator->total(),
            'pagination' => [
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'cities' => $this->search->publicListings()
                ->select('city')
                ->distinct()
                ->whereNotNull('city')
                ->orderBy('city')
                ->pluck('city'),
            'zones' => $this->search->publicListings()
                ->select('zone')
                ->distinct()
                ->whereNotNull('zone')
                ->orderBy('zone')
                ->pluck('zone'),
            'featured' => $featured,
            'justListed' => $this->search->publicListings()
                ->with(['owner:id,name,email,verified,badge_tier', 'images'])
                ->latest()
                ->take(8)
                ->get()
                ->loadCount(['views', 'favouritedBy as favourites_count']),
            'filters' => array_filter($filters, fn ($value) => $value !== null),
            'favouriteIds' => $viewer
                ? app(\App\Services\FavouriteService::class)->idsFor($viewer)
                : [],
            'marketplace' => [
                'amenities' => (array) $this->config->get('marketplace.amenities', []),
                'badges' => (array) $this->config->get('marketplace.badges', []),
                'featuredPosition' => $this->config->get('marketplace.featured_position', 'top'),
                'quickViewEnabled' => (bool) $this->config->get('marketplace.quick_view_enabled', true),
                'compareEnabled' => (bool) $this->config->get('marketplace.compare_enabled', true),
                'compareMax' => (int) $this->config->get('marketplace.compare_max', 3),
                'mapEnabled' => (bool) $this->config->get('marketplace.map_enabled', true),
                'mapCenter' => (array) $this->config->get('marketplace.map_default_center', ['lat' => -17.8292, 'lng' => 31.0522, 'zoom' => 11]),
                'recommendations' => (bool) $this->config->get('marketplace.recommendations_enabled', true),
                'suggestionUrl' => route('search.suggestions'),
                'popular' => (array) $this->config->get('marketplace.popular_threshold', ['views' => 60, 'saves' => 2]),
            ],
            'explore' => $this->exploreAreas(),
            'recommended' => $isTenant
                ? $this->recommendations->recommendFor($viewer)
                : collect(),
            'recentlyViewed' => $isTenant
                ? $this->recommendations->recentlyViewedFor($viewer)
                : collect(),
        ]);
    }

    /**
     * Areas for the "Explore by area" section: every area that currently has
     * live listings (busiest first), then filled up with popular areas from
     * the location catalogue so the section stays rich before every
     * neighbourhood has a listing. Each still links to a real filtered search.
     *
     * @return \Illuminate\Support\Collection<int, array{suburb: string, city: string, total: int}>
     */
    private function exploreAreas(int $limit = 12): \Illuminate\Support\Collection
    {
        $counts = $this->search->publicListings()
            ->selectRaw('suburb, city, COUNT(*) as total')
            ->whereNotNull('suburb')
            ->groupBy('suburb', 'city')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'suburb' => $row->suburb,
                'city' => $row->city,
                'total' => (int) $row->total,
            ]);

        if ($counts->count() >= $limit) {
            return $counts->take($limit)->values();
        }

        $seen = $counts->map(fn ($a) => $a['suburb'].'|'.$a['city'])->all();

        $popular = Location::suburbs()->popular()
            ->whereNotNull('city')
            ->orderBy('city')
            ->orderBy('name')
            ->get(['name', 'city'])
            ->reject(fn ($row) => in_array($row->name.'|'.$row->city, $seen, true))
            ->map(fn ($row) => [
                'suburb' => $row->name,
                'city' => $row->city,
                'total' => 0,
            ]);

        return $counts->concat($popular)->take($limit)->values();
    }

    /**
     * JSON autocomplete endpoint for the hero search bar.
     */
    public function suggest(Request $request)
    {
        return response()->json(
            $this->search->suggestions($request->get('q'), (int) $request->get('limit', 6))
        );
    }

    /**
     * Coerce and whitelist query params into a normalized filter set.
     *
     * @return array<string, mixed>
     */
    private function normalizeFilters(Request $request): array
    {
        $types = array_keys(Property::TYPES);
        $sorts = ['newest', 'recently_updated', 'price_asc', 'price_desc', 'price_per_m2', 'featured', 'top_rated'];
        $options = [
            'payment_terms' => Property::PAYMENT_TERMS,
            'security_type' => Property::SECURITY_TYPES,
            'parking_type' => Property::PARKING_TYPES,
            'preferred_tenant' => Property::PREFERRED_TENANTS,
        ];
        $config = $this->config;

        return [
            'q' => $request->filled('q') ? trim((string) $request->q) : null,
            'property_type' => $request->filled('property_type') && in_array($request->property_type, $types, true)
                ? (string) $request->property_type
                : null,
            'city' => $request->filled('city') ? (string) $request->city : null,
            'zone' => $request->filled('zone') ? (string) $request->zone : null,
            'suburb' => $request->filled('suburb') ? (string) $request->suburb : null,
            'payment_terms' => $request->filled('payment_terms') && in_array($request->payment_terms, $options['payment_terms'], true)
                ? (string) $request->payment_terms
                : null,
            'security_type' => $request->filled('security_type') && in_array($request->security_type, $options['security_type'], true)
                ? (string) $request->security_type
                : null,
            'parking_type' => $request->filled('parking_type') && in_array($request->parking_type, $options['parking_type'], true)
                ? (string) $request->parking_type
                : null,
            'preferred_tenant' => $request->filled('preferred_tenant') && in_array($request->preferred_tenant, $options['preferred_tenant'], true)
                ? (string) $request->preferred_tenant
                : null,
            'min_price' => is_numeric($request->min_price) ? (float) $request->min_price : null,
            'max_price' => is_numeric($request->max_price) ? (float) $request->max_price : null,
            'bedrooms' => is_numeric($request->bedrooms) ? (int) $request->bedrooms : null,
            'bathrooms' => is_numeric($request->bathrooms) ? (int) $request->bathrooms : null,
            'furnished' => $request->filled('furnished') && in_array($request->furnished, ['1', '0', 'true', 'false'], true)
                ? filter_var($request->furnished, FILTER_VALIDATE_BOOLEAN)
                : null,
            'verified' => $request->filled('verified') && in_array($request->verified, ['1', '0', 'true', 'false'], true)
                ? filter_var($request->verified, FILTER_VALIDATE_BOOLEAN)
                : null,
            'availability' => in_array($request->availability, ['now', 'upcoming'], true)
                ? (string) $request->availability
                : null,
            'amenities' => $request->filled('amenities')
                ? array_values(array_filter((array) $request->amenities, fn ($value) => in_array($value, array_keys((array) $config->get('marketplace.amenities', [])), true)))
                : null,
            'lat' => is_numeric($request->lat) ? (float) $request->lat : null,
            'lng' => is_numeric($request->lng) ? (float) $request->lng : null,
            'radius_km' => is_numeric($request->radius_km) ? (float) $request->radius_km : null,
            'sort' => in_array($request->sort, $sorts, true) ? (string) $request->sort : null,
            'sort_direction' => in_array($request->sort_direction, ['asc', 'desc'], true) ? (string) $request->sort_direction : null,
        ];
    }
}