<?php

namespace App\Http\Controllers;

use App\Models\RentalApplication;
use App\Services\ApplicationService;
use App\Services\FavouriteService;
use App\Services\PropertySearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicPropertyController extends Controller
{
    public function __construct(
        private readonly PropertySearchService $search,
        private readonly FavouriteService $favourites,
    ) {
    }

    /**
     * Show the public detail page for an available property.
     */
    public function show(Request $request, int $id)
    {
        $property = $this->search->findPublicDetail($id);

        abort_unless($property, 404);

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

        return Inertia::render('Marketplace/Show', [
            'property' => $property,
            'isFavourited' => $request->user()
                ? in_array($id, $this->favourites->idsFor($request->user()), true)
                : false,
            'viewingSlots' => $viewingSlots,
            'applicationState' => $applicationState,
        ]);
    }
}