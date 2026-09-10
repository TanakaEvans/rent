<?php

namespace App\Http\Controllers;

use App\Services\SavedSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function __construct(private readonly SavedSearchService $savedSearches)
    {
    }

    /**
     * The tenant's saved searches with their current match counts.
     */
    public function index(Request $request): Response
    {
        $searches = $this->savedSearches->listFor($request->user())->get();

        $searches->each(function ($search) use ($request) {
            $criteria = array_merge((array) $search->criteria, ['per_page' => 1]);
            $search->match_count = app(\App\Services\PropertySearchService::class)->search($criteria)->total();
        });

        return Inertia::render('Tenant/SavedSearches', [
            'searches' => $searches,
        ]);
    }

    /**
     * Persist the current marketplace filters as a saved search.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'criteria' => 'required|array',
            'notify' => 'boolean',
        ]);

        $this->savedSearches->create(
            $request->user(),
            $validated['name'],
            $validated['criteria'],
            (bool) ($validated['notify'] ?? false),
        );

        return redirect()->route('tenant.saved-searches.index')
            ->with('success', 'Saved search created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'criteria' => 'sometimes|array',
            'notify' => 'sometimes|boolean',
        ]);

        $this->savedSearches->update($request->user(), $id, $validated);

        return redirect()->route('tenant.saved-searches.index')
            ->with('success', 'Saved search updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->savedSearches->delete($request->user(), $id);

        return redirect()->route('tenant.saved-searches.index')
            ->with('success', 'Saved search removed.');
    }
}