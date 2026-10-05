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

        // Guided profile builder: new accounts land on their profile first
        // (photo, phone, details) — they can skip to the dashboard any time.
        return redirect($isOwner ? route('account.profile') : route('tenant.profile'))
            ->with('success', $isOwner
                ? 'Welcome to ZimRent! Build your profile — add a photo and your details, then add your first property. You can skip to your dashboard any time.'
                : 'Welcome to ZimRent! Build your profile — add a photo and your details so owners reply faster. You can skip to your dashboard any time.');
    }
}
