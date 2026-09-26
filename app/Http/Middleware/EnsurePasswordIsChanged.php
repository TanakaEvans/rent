<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // A deactivated account loses its session on the very next request.
        if ($user && $user->status === 'inactive') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', AuthService::DEACTIVATED_MESSAGE);
        }

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
