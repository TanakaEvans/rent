<?php

namespace App\Http\Controllers;

use App\Models\AdPackage;
use App\Models\AdPlacement;
use App\Models\Property;
use App\Services\AdPlacementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdPlacementController extends Controller
{
    public function __construct(private readonly AdPlacementService $advertisements)
    {
    }

    /**
     * Owner advertising hub: catalogue, my placements with stats, bookings.
     */
    public function ownerIndex(Request $request): Response
    {
        return Inertia::render('Owner/Advertising', $this->advertisements->ownerIndex($request->user()));
    }

    /**
     * Book a promotion for one of the owner's available listings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|integer',
            'package_id' => 'required|integer',
        ]);

        $property = Property::where('owner_id', $request->user()->id)
            ->findOrFail((int) $validated['property_id']);

        $package = AdPackage::active()->findOrFail((int) $validated['package_id']);

        try {
            $this->advertisements->book($request->user(), $property, $package);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('owner.advertising.index')
            ->with('success', 'Promotion booked.');
    }

    /**
     * Admin moderation & approval queue.
     */
    public function adminIndex(): Response
    {
        return Inertia::render('Admin/Advertising', $this->advertisements->adminIndex());
    }

    /**
     * Staff approve a reserved order — payment gateway + window activation.
     */
    public function adminApprove(int $id)
    {
        $placement = AdPlacement::findOrFail($id);

        try {
            $this->advertisements->approve($placement);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Placement approved — the promotion window is live.');
    }

    /**
     * Staff cancel a placement (prorated credit) or void a reserved order.
     */
    public function adminCancel(Request $request, int $id)
    {
        $placement = AdPlacement::findOrFail($id);
        $note = $request->validate(['note' => 'nullable|string|max:500'])['note'] ?? null;

        try {
            $this->advertisements->cancel($placement, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Placement cancelled.');
    }

    /**
     * Pause / resume a live placement (moderation freeze).
     */
    public function adminPause(Request $request, int $id)
    {
        $placement = AdPlacement::findOrFail($id);
        $note = $request->validate(['note' => 'nullable|string|max:500'])['note'] ?? null;

        try {
            $this->advertisements->pause($placement, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Placement paused.');
    }

    public function adminResume(Request $request, int $id)
    {
        $placement = AdPlacement::findOrFail($id);
        $note = $request->validate(['note' => 'nullable|string|max:500'])['note'] ?? null;

        try {
            $this->advertisements->resume($placement, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Placement resumed — the window was extended.');
    }
}