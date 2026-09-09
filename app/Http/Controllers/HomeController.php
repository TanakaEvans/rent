<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\PropertySearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(private readonly PropertySearchService $search)
    {
    }

    /**
     * Show the public marketplace landing page.
     */
    public function index(Request $request)
    {
        $filters = $this->normalizeFilters($request);

        $paginator = $this->search->search($filters);

        return Inertia::render('Marketplace/Index', [
            'properties' => $paginator->items(),
            'total' => $paginator->total(),
            'pagination' => [
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'cities' => Property::listed()
                ->select('city')
                ->distinct()
                ->whereNotNull('city')
                ->orderBy('city')
                ->pluck('city'),
            'zones' => Property::listed()
                ->select('zone')
                ->distinct()
                ->whereNotNull('zone')
                ->orderBy('zone')
                ->pluck('zone'),
            'featured' => Property::listed()->featured()->latest()->take(1)->get(),
            'filters' => array_filter($filters, fn ($value) => $value !== null),
            'favouriteIds' => $request->user()
                ? app(\App\Services\FavouriteService::class)->idsFor($request->user())
                : [],
        ]);
    }

    /**
     * Coerce and whitelist query params into a normalized filter set.
     *
     * @return array<string, mixed>
     */
    private function normalizeFilters(Request $request): array
    {
        $types = array_keys(Property::TYPES);

        return [
            'property_type' => $request->filled('property_type') && in_array($request->property_type, $types, true)
                ? (string) $request->property_type
                : null,
            'city' => $request->filled('city') ? (string) $request->city : null,
            'zone' => $request->filled('zone') ? (string) $request->zone : null,
            'suburb' => $request->filled('suburb') ? (string) $request->suburb : null,
            'min_price' => is_numeric($request->min_price) ? (float) $request->min_price : null,
            'max_price' => is_numeric($request->max_price) ? (float) $request->max_price : null,
            'bedrooms' => is_numeric($request->bedrooms) ? (int) $request->bedrooms : null,
            'bathrooms' => is_numeric($request->bathrooms) ? (int) $request->bathrooms : null,
            'furnished' => $request->filled('furnished') && in_array($request->furnished, ['1', '0', 'true', 'false'], true)
                ? filter_var($request->furnished, FILTER_VALIDATE_BOOLEAN)
                : null,
            'sort' => in_array($request->sort, ['newest', 'price_asc', 'price_desc'], true)
                ? (string) $request->sort
                : null,
        ];
    }
}