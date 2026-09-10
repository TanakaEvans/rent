<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyView;
use App\Models\RentalApplication;
use App\Services\AdPlacementService;
use App\Services\ConfigurationService;
use App\Services\FavouriteService;
use App\Services\PropertySearchService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicPropertyController extends Controller
{
    public function __construct(
        private readonly PropertySearchService $search,
        private readonly FavouriteService $favourites,
        private readonly RecommendationService $recommendations,
        private readonly ConfigurationService $config,
        private readonly AdPlacementService $advertisements,
    ) {
    }

    /**
     * Show the public detail page for an available property.
     */
    public function show(Request $request, int $id)
    {
        $property = $this->search->findPublicDetail($id);

        abort_unless($property, 404);

        // Record the view silently (analytics + recently-viewed + popular) and,
        // for a promoted listing, a click-through event (M13 FR-05/NFR-03).
        if (! $request->user()?->is($property->owner)) {
            PropertyView::create([
                'property_id' => $property->id,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'viewed_at' => now(),
            ]);

            $this->advertisements->trackForProperty($property, 'click');
        }

        $viewingSlots = null;
        if ($request->user()?->hasRole('Tenant')) {
            $viewingSlots = $property->viewingSlots
                ->where('status', 'available')
                ->filter(fn ($slot) => $slot->ends_at->isFuture())
                ->values()
                ->map(fn ($slot) => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at,
                    'ends_at' => $slot->ends_at,
                ]);
        }

        $applicationState = null;
        if ($request->user()?->hasRole('Tenant')) {
            $applicationState = RentalApplication::where('property_id', $property->id)
                ->where('applicant_id', $request->user()->id)
                ->latest()
                ->first();
        }

        $viewer = $request->user();
        $isTenant = $viewer?->hasRole('Tenant');

        $similar = Property::listed()
            ->with(['images', 'owner:id,name,email,verified'])
            ->where('id', '!=', $property->id)
            ->where(function ($query) use ($property) {
                $query->where('property_type', $property->property_type)
                    ->orWhere('city', $property->city);
            })
            ->latest()
            ->take(4)
            ->get();

        return Inertia::render('Marketplace/Show', [
            'property' => $property,
            'similar' => $similar,
            'isFavourited' => $viewer
                ? in_array($id, $this->favourites->idsFor($viewer), true)
                : false,
            'viewingSlots' => $viewingSlots,
            'applicationState' => $applicationState,
            'reportCategories' => (array) $this->config->get('marketplace.report_categories', [
                'suspicious_listing', 'incorrect_information', 'duplicate', 'wrong_price', 'fraud_concern', 'already_rented', 'inappropriate_content',
            ]),
            'recents' => $isTenant ? $this->recommendations->recentlyViewedFor($viewer) : collect(),
            'recommended' => $isTenant ? $this->recommendations->recommendFor($viewer) : collect(),
        ]);
    }
}