<?php

namespace App\Http\Middleware;

use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the free-then-paid access model (Module 24 — `access.*`).
 *
 * Attach ONLY to the action routes that a payer must hold a pass to use
 * (creating a listing as an owner; enquiring / applying / expressing interest
 * as a tenant). It never guards browsing, dashboards, login/logout, the access
 * page itself or the purchase route, so a payer without a pass can still reach
 * the Access page and buy one.
 *
 * While `access.charge_enabled` is false (the shipped default) or the free
 * window is still open, {@see AccessService::hasActiveAccess()} is always true
 * and this middleware is a complete no-op.
 */
class EnsureHasAccess
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->access->hasActiveAccess($user)) {
            return redirect()->route('access.index')->with(
                'warning',
                'An access pass is required to continue. Please activate your access pass below.'
            );
        }

        return $next($request);
    }
}
