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

    public function create()
    {
        if (auth()->check()) {
            return redirect($this->authService->landingUrlFor(auth()->user()) ?? route('dashboard'));
        }

        return Inertia::render('Auth/Register');
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
            'terms' => ['required', 'accepted'],
        ]);

        $user = $this->authService->registerTenant($validated);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->authService->landingUrlFor($user) ?? route('dashboard'))
            ->with('success', 'Welcome to ZimRent! Your tenant account is ready.');
    }
}
