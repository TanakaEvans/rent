<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function create(Request $request)
    {
        if (auth()->check()) {
            return redirect($this->authService->landingUrlFor(auth()->user()) ?? route('dashboard'));
        }

        return Inertia::render('Auth/Register', [
            // "List your property" links here with ?as=owner to preselect the tab.
            'defaultRole' => $request->query('as') === 'owner' ? 'owner' : 'tenant',
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->check()) {
            return redirect($this->authService->landingUrlFor(auth()->user()) ?? route('dashboard'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:auth_users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['nullable', 'in:tenant,owner'],
            'terms' => ['required', 'accepted'],
        ]);

        $isOwner = ($validated['role'] ?? 'tenant') === 'owner';

        $user = $isOwner
            ? $this->authService->registerOwner($validated)
            : $this->authService->registerTenant($validated);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->authService->landingUrlFor($user) ?? route('dashboard'))
            ->with('success', $isOwner
                ? 'Welcome to ZimRent! Your owner account is ready — add your first property.'
                : 'Welcome to ZimRent! Your tenant account is ready.');
    }
}
