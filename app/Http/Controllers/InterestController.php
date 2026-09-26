<?php

namespace App\Http\Controllers;

use App\Models\ExpressInterest;
use App\Models\Property;
use App\Services\InterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterestController extends Controller
{
    public function __construct(private readonly InterestService $service)
    {
    }

    /**
     * Express interest in an available property (tenant). One row per
     * tenant×property; re-express re-opens an archived one.
     */
    public function express(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $outcome = $this->service->express($request->user(), $property, $data['note'] ?? null);

        return redirect()->back()->with('success', match ($outcome) {
            InterestService::EXPRESSED_NEW => 'Interest recorded — the owner will be in touch.',
            InterestService::EXPRESSED_REOPENED => 'Interest re-expressed — the owner has been notified again.',
            default => 'You already expressed interest in this property.',
        });
    }

    /**
     * The tenant's own interest queue.
     */
    public function tenantIndex(Request $request): Response
    {
        return Inertia::render('Tenant/Interests', [
            'interests' => $this->service->listForTenant($request->user()),
        ]);
    }

    /**
     * Withdraw (archive) one of the tenant's own interests.
     */
    public function withdraw(Request $request, ExpressInterest $interest): RedirectResponse
    {
        $this->service->withdraw($request->user(), $interest);

        return redirect()->back()->with('success', 'Interest withdrawn.');
    }

    /**
     * The owner's queue, grouped by property.
     */
    public function ownerIndex(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $propertyId = $request->integer('property', null);
        $propertyId = $propertyId > 0 ? $propertyId : null;

        return Inertia::render('Owner/Interests/Index', [
            'queue' => $this->service->queueForOwner($request->user(), $propertyId, $status)
                ->map(function (Property $property) {
                    return [
                        'id' => $property->id,
                        'title' => $property->title,
                        'city' => $property->city,
                        'status' => $property->status,
                        'image' => $property->images->first(),
                        'interests' => $property->interests->map(function (ExpressInterest $interest) {
                            return $this->interestRow($interest);
                        }),
                    ];
                }),
            'counts' => $this->service->countsForOwner($request->user()),
            'filters' => [
                'status' => in_array($status, ExpressInterest::STATUSES, true) ? $status : null,
                'property' => $propertyId,
            ],
            'properties' => Property::where('owner_id', $request->user()->id)
                ->where('status', '!=', 'draft')
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    /**
     * Mark one queue row as contacted.
     */
    public function ownerContact(Request $request, ExpressInterest $interest): RedirectResponse
    {
        $this->service->markContacted($request->user(), $interest);

        return redirect()->back()->with('success', 'Marked as contacted.');
    }

    /**
     * Pull a contacted row back into the active queue.
     */
    public function ownerReopen(Request $request, ExpressInterest $interest): RedirectResponse
    {
        $this->service->reopen($request->user(), $interest);

        return redirect()->back()->with('success', 'Interest moved back to the active queue.');
    }

    /**
     * Archive a queue row (leaves the active queue, kept for history).
     */
    public function ownerArchive(Request $request, ExpressInterest $interest): RedirectResponse
    {
        $this->service->archive($request->user(), $interest);

        return redirect()->back()->with('success', 'Interest archived.');
    }

    /**
     * The row presented to the owner UI (tenant profile + badge surface so
     * the owner can judge the lead before making contact).
     */
    private function interestRow(ExpressInterest $interest): array
    {
        $profile = $interest->tenant->tenantProfile;

        return [
            'id' => $interest->id,
            'status' => $interest->status,
            'note' => $interest->note,
            'expressed_at' => $interest->created_at,
            'tenant' => [
                'id' => $interest->tenant->id,
                'name' => $interest->tenant->name,
                'email' => $interest->tenant->email,
                'badge_tier' => $interest->tenant->badge_tier,
                'phone' => $profile?->phone,
                'city' => $profile?->city,
                'employment_status' => $profile?->employment_status,
                'salary_band' => $profile?->salary_band,
                'preferred_contact' => $profile?->preferred_contact,
            ],
        ];
    }
}