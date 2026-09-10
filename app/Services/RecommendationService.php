<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyView;
use App\Models\RentalApplication;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Marketplace recommendations + recently viewed (Marketplace §43/§46).
 * Signals come from the tenant's views, favourites, enquiries and
 * applications; with no signals yet it falls back to what's popular.
 */
class RecommendationService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * The listed properties the tenant has viewed most recently.
     *
     * @return Collection<int, Property>
     */
    public function recentlyViewedFor(User $user)
    {
        $limit = max(1, (int) $this->config->get('marketplace.recently_viewed_limit', 6));

        $ids = PropertyView::query()
            ->where('user_id', $user->id)
            ->select('property_id')
            ->selectRaw('MAX(viewed_at) as last_viewed')
            ->groupBy('property_id')
            ->orderByRaw('MAX(viewed_at) desc')
            ->limit($limit * 3)
            ->pluck('property_id')
            ->all();

        if (! $ids) {
            return collect();
        }

        $properties = Property::listed()
            ->with('owner:id,name,email')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $properties->get($id))
            ->filter()
            ->values()
            ->take($limit);
    }

    /**
     * Personalized picks for a logged-in tenant, weighted by their viewing
     * history. Falls back to what's popular when the tenant has no signals.
     *
     * @return Collection<int, Property>
     */
    public function recommendFor(User $user)
    {
        $limit = max(1, (int) $this->config->get('marketplace.recommendations_limit', 4));

        $signals = $this->signalsFor($user);

        if (! $signals['historyCount']) {
            return $this->popular($limit);
        }

        $viewedIds = PropertyView::query()
            ->where('user_id', $user->id)
            ->pluck('property_id');

        $favouriteIds = $user->favouritedProperties()->pluck('properties.id');
        $appliedIds = RentalApplication::query()->where('applicant_id', $user->id)->pluck('property_id');

        return Property::listed()
            ->with(['owner:id,name,email'])
            ->whereNotIn('id', $viewedIds)
            ->whereNotIn('id', $favouriteIds)
            ->whereNotIn('id', $appliedIds)
            ->get()
            ->sortByDesc(fn (Property $property) => $this->score($property, $signals))
            ->sortByDesc(fn (Property $property) => (int) $property->featured)
            ->take($limit)
            ->values();
    }

    /**
     * The most-viewed public listings in the last 30 days.
     *
     * @return Collection<int, Property>
     */
    public function popular(int $limit = 6)
    {
        $ids = PropertyView::query()
            ->where('viewed_at', '>=', now()->subDays(30))
            ->select('property_id')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('property_id')
            ->orderByRaw('COUNT(*) desc')
            ->orderByDesc('property_id')
            ->limit($limit * 3)
            ->pluck('property_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! $ids) {
            return Property::listed()->with('owner:id,name,email')->latest()->take($limit)->get();
        }

        $properties = Property::listed()
            ->with('owner:id,name,email')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $properties->get($id))
            ->filter()
            ->values()
            ->take($limit);
    }

    /**
     * Aggregate the tenant's marketplace signals into a scoring profile.
     */
    private function signalsFor(User $user): array
    {
        $views = PropertyView::query()
            ->where('user_id', $user->id)
            ->with('property:id,property_type,suburb,city,bedrooms,price,amenities')
            ->get()
            ->pluck('property');

        $favourites = $user->favouritedProperties()
            ->get(['properties.id', 'property_type', 'suburb', 'city', 'bedrooms', 'price', 'amenities']);

        $applied = RentalApplication::query()
            ->where('applicant_id', $user->id)
            ->whereHas('property')
            ->with('property:id,property_type,suburb,city,bedrooms,price,amenities')
            ->get()
            ->pluck('property');

        $all = $views->concat($favourites)->concat($applied)->filter();

        return [
            'historyCount' => $all->count(),
            'type' => $all->groupBy('property_type')->map->count()->sortDesc()->keys()->first(),
            'suburb' => $all->groupBy('suburb')->map->count()->sortDesc()->keys()->first(),
            'city' => $all->groupBy('city')->map->count()->sortDesc()->keys()->first(),
            'maxBedrooms' => $all->max('bedrooms'),
            'maxPrice' => (float) $all->max('price') * 1.15,
            'amenities' => $all->flatMap(fn ($p) => (array) $p->amenities)->unique()->take(6)->all(),
        ];
    }

    private function score(Property $property, array $signals): int
    {
        $score = 0;

        if ($signals['type'] && $property->property_type === $signals['type']) {
            $score += 6;
        }
        if ($signals['suburb'] && $property->suburb === $signals['suburb']) {
            $score += 5;
        }
        if ($signals['city'] && $property->city === $signals['city']) {
            $score += 3;
        }
        if ($signals['maxBedrooms'] !== null && (int) $property->bedrooms <= (int) $signals['maxBedrooms']) {
            $score += 2;
        }
        if ($signals['maxPrice'] && (float) $property->price <= $signals['maxPrice']) {
            $score += 2;
        }
        if ((int) $property->verified) {
            $score += 1;
        }

        $overlap = count(array_intersect($signals['amenities'], (array) $property->amenities));
        $score += $overlap;

        return $score;
    }
}