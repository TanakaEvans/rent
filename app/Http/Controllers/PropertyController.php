<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\ConfigurationService;
use App\Services\ListingLifecycleService;
use App\Services\PropertyService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertyService $properties,
        private readonly ConfigurationService $config,
        private readonly ListingLifecycleService $lifecycle,
    ) {
    }

    /**
     * List the signed-in owner's properties with their listing window.
     */
    public function index()
    {
        $owner = auth()->user();

        $properties = Property::withCount('applications')
            ->where('owner_id', $owner->id)
            ->latest()
            ->get()
            ->each(fn (Property $property) => $property->setAttribute('listing', $this->lifecycle->stateFor($property)));

        return Inertia::render('Owner/Properties/Index', [
            'properties' => $properties,
        ]);
    }

    /**
     * Show the property creation form.
     */
    public function create()
    {
        return Inertia::render('Owner/Properties/Create', [
            'amenityOptions' => $this->amenityOptions(),
        ]);
    }

    /**
     * Store a new property owned by the signed-in owner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true), $this->locationMessages());

        $property = $this->properties->create($validated, $request->user(), [
            'cover' => $request->file('cover'),
            'images' => $validated['images'] ?? [],
        ]);

        return redirect()->route('owner.properties.show', $property)
            ->with('success', 'Property created successfully.');
    }

    /**
     * Show one of the owner's properties.
     */
    public function show(int $id)
    {
        $owner = auth()->user();
        $property = $this->properties->findOwned($id, $owner);

        $siblings = Property::where('owner_id', $owner->id)
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $position = array_search($property->id, $siblings, true);

        $navigation = [
            'prev' => $position > 0 ? $siblings[$position - 1] : null,
            'next' => $position !== false && $position < count($siblings) - 1 ? $siblings[$position + 1] : null,
        ];

        return Inertia::render('Owner/Properties/Show', [
            'property' => $property,
            'navigation' => $navigation,
            'listing' => $this->lifecycle->stateFor($property),
            'deleteBlockedReason' => $this->properties->deleteBlockReason($property),
        ]);
    }

    /**
     * Show the property edit form.
     */
    public function edit(int $id)
    {
        $property = $this->properties->findOwned($id, auth()->user());

        return Inertia::render('Owner/Properties/Edit', [
            'property' => $property,
            'amenityOptions' => $this->amenityOptions(),
        ]);
    }

    /**
     * Update an owned property's details. Status is not part of the edit —
     * it only moves through the status and renew actions. The edit form
     * always submits `cover_image` (the retained cover, or empty once
     * removed), so its gallery is authoritative; a request carrying neither
     * `images` nor `cover_image` leaves the gallery untouched.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate($this->rules(false), $this->locationMessages());

        $media = ['cover' => $request->file('cover')];
        if ($request->exists('images') || $request->exists('cover_image')) {
            $media['images'] = $validated['images'] ?? [];
        }
        if ($request->exists('cover_image')) {
            $media['cover_image'] = $validated['cover_image'] ?? null;
        }

        $property = $this->properties->update($id, $validated, $request->user(), $media);

        return redirect()->route('owner.properties.show', $property)
            ->with('success', 'Property updated successfully.');
    }

    /**
     * Delete an owned property.
     */
    public function destroy(int $id, Request $request)
    {
        $this->properties->delete($id, $request->user());

        return redirect()->route('owner.properties.index')
            ->with('success', 'Property deleted successfully.');
    }

    /**
     * Move a property through the status state machine and log the transition.
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:available,reserved,occupied,unavailable',
        ]);

        $property = $this->properties->changeStatus($id, $validated['status'], $request->user());

        return redirect()->route('owner.properties.show', $property)
            ->with('success', 'Property status updated to '.Property::STATUSES[$property->status].'.');
    }

    /**
     * Renew a listing for another validity period (Marketplace §41).
     */
    public function renew(Request $request, int $id)
    {
        $property = $this->properties->findOwned($id, $request->user());

        $renewed = $this->lifecycle->renew($property, $request->user());

        return redirect()->route('owner.properties.show', $renewed)
            ->with('success', 'Listing renewed. It will stay live until '.$renewed->expires_at->format('d M Y').'.');
    }

    /**
     * Shared validation rules for the preservation-release listing form.
     * Every answer is an option drawn from the Property constants. Only the
     * create form picks the initial status.
     */
    private function rules(bool $creating): array
    {
        $imageEntry = function ($attribute, $value, $fail) {
            if ($value instanceof UploadedFile) {
                if (! $value->isValid() || ! str_starts_with((string) $value->getMimeType(), 'image/') || $value->getSize() > 8192 * 1024) {
                    $fail('Each photo must be a valid image file under 8 MB.');
                }
                return;
            }
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 255) {
                $fail('Each photo must be an uploaded image or an existing photo path.');
            }
        };

        return [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'property_type' => ['required', Rule::in(array_keys(Property::TYPES))],
            'bedrooms' => 'required|integer|min:0|max:50',
            'bathrooms' => 'required|integer|min:0|max:50',
            'building_size' => 'nullable|numeric|min:0',
            'land_size' => 'nullable|numeric|min:0',
            'floor_area' => 'nullable|numeric|min:0',
            'year_built' => 'nullable|integer|min:1900|max:2100',
            'price' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'currency' => ['required', Rule::in(Property::CURRENCIES)],
            'payment_terms' => ['required', Rule::in(Property::PAYMENT_TERMS)],
            'water_cost' => 'nullable|numeric|min:0',
            'electricity_cost' => 'nullable|numeric|min:0',
            'trash_cost' => 'nullable|numeric|min:0',
            'negotiable' => 'boolean',
            'furnished' => 'boolean',
            'entrance_type' => 'nullable|in:own,shared',
            'bathroom_type' => 'nullable|in:own,shared',
            'parking_type' => ['nullable', Rule::in(Property::PARKING_TYPES)],
            'families_allowed' => 'nullable|boolean',
            'distance_to_cbd' => 'nullable|numeric|min:0',
            'security_type' => ['required', Rule::in(Property::SECURITY_TYPES)],
            'children_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
            'smoking_allowed' => 'boolean',
            'parties_allowed' => 'boolean',
            'minimum_stay' => 'required|integer|min:0|max:120',
            'preferred_tenant' => ['required', Rule::in(Property::PREFERRED_TENANTS)],
            'landlord_type' => ['required', Rule::in(Property::LANDLORD_TYPES)],
            'contact_preference' => ['required', Rule::in(Property::CONTACT_PREFERENCES)],
            'show_phone' => 'boolean',
            'landmark' => 'nullable|string|max:255',
            'status' => $creating ? 'required|in:available,reserved,occupied,unavailable' : 'exclude',
            'suburb' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            // The exact pin is mandatory: it is what the platform shares with a
            // tenant once the owner accepts their viewing. The public map only
            // ever shows an approximate area derived from it.
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:60',
            'cover_image' => 'nullable|string|max:255',
            'cover' => 'nullable|image|max:8192',
            'images' => 'nullable|array|max:8',
            'images.*' => ['required', $imageEntry],
            'available_from' => 'nullable|date',
        ];
    }

    /**
     * Friendly validation messages for the mandatory precise-location pin.
     *
     * @return array<string, string>
     */
    private function locationMessages(): array
    {
        $pin = 'Drop the exact pin on the map (or tap “Use my current location”) so we can share the precise location once you accept a viewing.';

        return [
            'latitude.required' => $pin,
            'longitude.required' => $pin,
            'latitude.numeric' => $pin,
            'longitude.numeric' => $pin,
            'latitude.between' => 'The map pin is out of range — set it again on the map.',
            'longitude.between' => 'The map pin is out of range — set it again on the map.',
        ];
    }

    /**
     * The amenity catalogue (key => label) shipped to the listing form so the
     * chips owners pick from always match the marketplace filter set.
     */
    private function amenityOptions(): array
    {
        return array_map(
            fn ($label, $key) => ['key' => $key, 'label' => $label],
            (array) $this->config->get('marketplace.amenities', []),
            array_keys((array) $this->config->get('marketplace.amenities', []))
        );
    }
}