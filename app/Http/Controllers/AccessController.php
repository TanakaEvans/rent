<?php

namespace App\Http\Controllers;

use App\Services\AccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner/tenant access-pass page for the configurable free-then-paid model
 * (Module 24 — `access.*`). Shows the current access position and records a
 * paid pass in test mode (no gateway — the pass is created immediately, exactly
 * like the subscription flow issues settled invoices without a gateway).
 */
class AccessController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * The access-pass status page for the signed-in user.
     */
    public function index(): Response
    {
        return Inertia::render('Access/Index', [
            'status' => $this->access->status(request()->user()),
        ]);
    }

    /**
     * Record an access pass for the signed-in user. A no-op (with a friendly
     * notice) while access is free, so it can never take money it shouldn't.
     */
    public function purchase(Request $request)
    {
        $user = $request->user();

        if (! $this->access->requiresPass($user)) {
            return redirect()->route('access.index')
                ->with('warning', 'Access is currently free — no pass is needed.');
        }

        $this->access->purchase($user);

        return redirect()->route('access.index')
            ->with('success', 'Your access pass is now active. Thank you!');
    }
}
