<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertyController extends Controller
{
    public function __construct(private readonly PropertyService $properties)
    {
    }

    /**
     * List the signed-in owner's properties.
     */
    public function index()
    {
        $owner = auth()->user();

        $properties = Property::withCount('applications')
            ->where('owner_id', $owner->id)
            ->latest()
            ->get();

        return Inertia::render('Owner/Properties/Index', [
            'properties' => $properties,
        ]);
    }

    /**
     * Show the property creation form.
     */
    public function create()
    {
        return Inertia::render('Owner/Properties/Create');
    }

    /**
     * Store a new property owned by the signed-in owner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'property_type' => 'required|in:house,flat,townhouse,cottage,room,commercial,land',
            'bedrooms' => 'required|integer|min:0|max:50',
            'bathrooms' => 'required|integer|min:0|max:50',
            'building_size' => 'nullable|numeric|min:0',
            'land_size' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'furnished' => 'boolean',
            'status' => 'required|in:available,reserved,occupied,unavailable',
            'suburb' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'amenities' => 'nullable|array',
            'cover_image' => 'nullable|string|max:255',
            'available_from' => 'nullable|date',
        ]);

        $property = $this->properties->create($validated, $request->user());

        return redirect()->route('owner.properties.show', $property)
            ->with('success', 'Property created successfully.');
    }

    /**
     * Show one of the owner's properties.
     */
    public function show(int $id)
    {
        $property = $this->properties->findOwned($id, auth()->user());

        return Inertia::render('Owner/Properties/Show', [
            'property' => $property,
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
        ]);
    }

    /**
     * Update an owned property.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'property_type' => 'required|in:house,flat,townhouse,cottage,room,commercial,land',
            'bedrooms' => 'required|integer|min:0|max:50',
            'bathrooms' => 'required|integer|min:0|max:50',
            'building_size' => 'nullable|numeric|min:0',
            'land_size' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'furnished' => 'boolean',
            'suburb' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'amenities' => 'nullable|array',
            'cover_image' => 'nullable|string|max:255',
            'available_from' => 'nullable|date',
        ]);

        $property = $this->properties->update($id, $validated, $request->user());

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
}