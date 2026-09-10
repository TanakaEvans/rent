<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyView;
use App\Models\Report;
use App\Models\RentalApplication;
use App\Models\SavedSearch;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Read-side marketplace analytics for owners (per property performance) and
 * admins (platform marketplace health). Pure aggregates — no writes.
 */
class MarketplaceAnalyticsService
{
    /**
     * Performance for one owner across all their properties, plus a 30-day
     * view trend for their portfolio.
     */
    public function ownerOverview(User $owner): array
    {
        $properties = Property::query()
            ->where('owner_id', $owner->id)
            ->withCount('views')
            ->withCount('favouritedBy as favourites_count')
            ->withCount('enquiries')
            ->withCount('applications')
            ->latest()
            ->get(['id', 'title', 'property_type', 'suburb', 'city', 'price', 'status', 'verified', 'featured', 'cover_image', 'expires_at', 'created_at']);

        $listed = $properties->where('status', 'available')->count();
        $reserved = $properties->where('status', 'reserved')->count();
        $occupied = $properties->where('status', 'occupied')->count();
        $unavailable = $properties->where('status', 'unavailable')->count();

        $viewsByDay = PropertyView::query()
            ->join('properties', 'properties.id', '=', 'property_views.property_id')
            ->where('properties.owner_id', $owner->id)
            ->where('property_views.viewed_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(property_views.viewed_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $trend = collect();

        foreach (range(29, 0) as $i) {
            $day = now()->subDays($i)->toDateString();
            $trend[] = ['day' => $day, 'views' => (int) ($viewsByDay[$day] ?? 0)];
        }

        $topProperty = $properties->sortByDesc('views_count')->first();

        return [
            'properties' => $properties,
            'totals' => [
                'properties' => $properties->count(),
                'listed' => $listed,
                'reserved' => $reserved,
                'occupied' => $occupied,
                'unavailable' => $unavailable,
                'views' => (int) $properties->sum('views_count'),
                'favourites' => (int) $properties->sum('favourites_count'),
                'enquiries' => (int) $properties->sum('enquiries_count'),
                'applications' => (int) $properties->sum('applications_count'),
            ],
            'trend' => $trend,
            'topProperty' => $topProperty?->id ? [
                'id' => $topProperty->id,
                'title' => $topProperty->title,
                'views' => (int) $topProperty->views_count,
            ] : null,
        ];
    }

    /**
     * Platform-wide marketplace vitals for the admin analytics page.
     */
    public function adminOverview(): array
    {
        $last30 = now()->subDays(29)->startOfDay();

        $listed = Property::listed()->get(['id', 'property_type', 'price', 'building_size', 'verified', 'featured', 'created_at']);

        $viewsByDay = PropertyView::query()
            ->where('viewed_at', '>=', $last30)
            ->selectRaw('DATE(viewed_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $trend = collect();
        foreach (range(29, 0) as $i) {
            $day = now()->subDays($i)->toDateString();
            $trend[] = ['day' => $day, 'views' => (int) ($viewsByDay[$day] ?? 0)];
        }

        $mostViewed = PropertyView::query()
            ->where('viewed_at', '>=', $last30)
            ->select('property_id')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('property_id')
            ->orderByRaw('COUNT(*) desc')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $property = Property::with('owner:id,name')->find($row->property_id);
                if (! $property) {
                    return null;
                }

                return [
                    'id' => $property->id,
                    'title' => $property->title,
                    'suburb' => $property->suburb,
                    'city' => $property->city,
                    'views' => (int) $row->views,
                ];
            })
            ->filter()
            ->values();

        $topAreas = PropertyView::query()
            ->join('properties', 'properties.id', '=', 'property_views.property_id')
            ->where('property_views.viewed_at', '>=', $last30)
            ->selectRaw("TRIM(CONCAT(COALESCE(properties.suburb, ''), ' ', COALESCE(properties.city, ''))) as area")
            ->selectRaw('COUNT(*) as views')
            ->groupBy('area')
            ->havingRaw("area <> ''")
            ->orderByRaw('COUNT(*) desc')
            ->limit(5)
            ->get();

        $byType = $listed->groupBy('property_type')->map->count();

        return [
            'totals' => [
                'listings' => (int) Property::count(),
                'listed' => $listed->count(),
                'featured' => (int) $listed->where('featured', true)->count(),
                'verified' => (int) $listed->where('verified', true)->count(),
                'views30d' => (int) PropertyView::where('viewed_at', '>=', $last30)->count(),
                'openReports' => (int) Report::whereIn('status', ['open', 'under_review'])->count(),
                'savedSearches' => (int) SavedSearch::count(),
                'enquiries30d' => (int) Enquiry::where('created_at', '>=', $last30)->count(),
                'applications30d' => (int) RentalApplication::where('created_at', '>=', $last30)->count(),
                'newTenants30d' => (int) User::where('created_at', '>=', $last30)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'Tenant'))
                    ->count(),
            ],
            'avgPrice' => round((float) $listed->avg('price'), 2),
            'avgPricePerM2' => round((float) $listed->filter(fn ($p) => (int) $p->building_size > 0)->avg(fn ($p) => (float) $p->price / (float) $p->building_size), 2),
            'byType' => $byType,
            'trend' => $trend,
            'mostViewed' => $mostViewed,
            'topAreas' => $topAreas,
        ];
    }
}