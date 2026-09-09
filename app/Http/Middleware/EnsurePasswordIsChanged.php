<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->password_changed_at === null || ($user->password_expires_at && $user->password_expires_at->isPast()))) {
            $currentRoute = $request->route()->getName();
            $allowedRoutes = [
                'password.change',
                'password.update',
                'logout',
                'login', // Just in case
            ];

            if (! in_array($currentRoute, $allowedRoutes)) {
                return redirect()->route('password.change')
                    ->with('warning', 'You must change your password before proceeding.');
            }
        }

        return $next($request);
    }
}
