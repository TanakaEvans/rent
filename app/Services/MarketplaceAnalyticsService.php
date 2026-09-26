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
use Illuminate\Support\Facades\DB;

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

        $listed = Property::listed()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured')
            ->selectRaw('SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified')
            ->selectRaw('AVG(price) as avg_price')
            ->selectRaw('AVG(CASE WHEN building_size > 0 THEN price / building_size END) as avg_price_per_m2')
            ->toBase()
            ->first();

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

        $viewCounts = PropertyView::query()
            ->where('viewed_at', '>=', $last30)
            ->select('property_id')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('property_id');

        $top = (clone $viewCounts)->orderByRaw('COUNT(*) desc')->orderByDesc('property_id')->limit(5)->get();
        $topProperties = Property::whereIn('id', $top->pluck('property_id'))->get(['id', 'title', 'suburb', 'city'])->keyBy('id');
        $mostViewed = $top
            ->map(function ($row) use ($topProperties) {
                $property = $topProperties->get($row->property_id);

                return $property ? [
                    'id' => $property->id,
                    'title' => $property->title,
                    'suburb' => $property->suburb,
                    'city' => $property->city,
                    'views' => (int) $row->views,
                ] : null;
            })
            ->filter()
            ->values();

        // Views are counted per listing first (uses the viewed_at index), then rolled up by area.
        $topAreas = DB::query()
            ->fromSub($viewCounts, 'v')
            ->join('properties', 'properties.id', '=', 'v.property_id')
            ->select('properties.suburb', 'properties.city')
            ->selectRaw('SUM(v.views) as views')
            ->groupBy('properties.suburb', 'properties.city')
            ->orderByRaw('SUM(v.views) desc')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['area' => trim(($row->suburb ?? '').' '.($row->city ?? '')), 'views' => (int) $row->views])
            ->filter(fn (array $row) => $row['area'] !== '')
            ->take(5)
            ->values();

        $byType = Property::listed()
            ->select('property_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('property_type')
            ->pluck('total', 'property_type')
            ->map(fn ($total) => (int) $total);

        return [
            'totals' => [
                'listings' => (int) Property::count(),
                'listed' => (int) $listed->total,
                'featured' => (int) $listed->featured,
                'verified' => (int) $listed->verified,
                'views30d' => (int) PropertyView::where('viewed_at', '>=', $last30)->count(),
                'openReports' => (int) Report::whereIn('status', ['open', 'under_review'])->count(),
                'savedSearches' => (int) SavedSearch::count(),
                'enquiries30d' => (int) Enquiry::where('created_at', '>=', $last30)->count(),
                'applications30d' => (int) RentalApplication::where('created_at', '>=', $last30)->count(),
                'newTenants30d' => (int) User::where('created_at', '>=', $last30)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'Tenant'))
                    ->count(),
            ],
            'avgPrice' => round((float) $listed->avg_price, 2),
            'avgPricePerM2' => round((float) $listed->avg_price_per_m2, 2),
            'byType' => $byType,
            'trend' => $trend,
            'mostViewed' => $mostViewed,
            'topAreas' => $topAreas,
        ];
    }
}