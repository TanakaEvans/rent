<?php

namespace App\Http\Controllers;

use App\Models\PropertyView;
use App\Models\RentalApplication;
use App\Services\AdPlacementService;
use App\Services\ConfigurationService;
use App\Services\FavouriteService;
use App\Services\InterestService;
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
        private readonly InterestService $interests,
    ) {
    }

    /**
     * Show the public detail page for an available property. Off-market
     * listings stay viewable (read-only) only for viewers with a legitimate
     * relationship to them (owner, admin, or a tenant with a lease,
     * application, enquiry, interest, favourite or report); everyone else
     * gets a 404.
     */
    public function show(Request $request, int $id)
    {
        $viewer = $request->user();
        $property = $this->search->findPublicDetail($id);
        $onMarket = $property !== null;

        if (! $onMarket && $viewer) {
            $property = $this->search->findRelatedDetail($id, $viewer);
        }

        abort_unless($property, 404);

        // Record the view silently (analytics + recently-viewed + popular) and,
        // for a promoted listing, a click-through event (M13 FR-05/NFR-03).
        if ($onMarket && ! $viewer?->is($property->owner)) {
            PropertyView::create([
                'property_id' => $property->id,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'viewed_at' => now(),
            ]);

            $this->advertisements->trackForProperty($property, 'click');
        }

        $isTenant = (bool) $viewer?->hasRole('Tenant');

        $viewingSlots = null;
        if ($onMarket && $isTenant) {
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
        if ($isTenant) {
            $applicationState = RentalApplication::where('property_id', $property->id)
                ->where('applicant_id', $viewer->id)
                ->latest()
                ->latest('id')
                ->first();
        }

        $similar = $this->search->publicListings()
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
            'onMarket' => $onMarket,
            'similar' => $similar,
            'isFavourited' => $viewer
                ? in_array($id, $this->favourites->idsFor($viewer), true)
                : false,
            'interestState' => $this->interests->stateFor($property->id, $viewer),
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