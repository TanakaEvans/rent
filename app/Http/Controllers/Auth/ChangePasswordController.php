<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ChangePasswordController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Auth/ChangePassword', [
            'forced' => $user->password_changed_at === null
                || ($user->password_expires_at && $user->password_expires_at->isPast()),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'password_expires_at' => now()->addMonths(5),
        ]);

        return redirect($this->authService->landingUrlFor($user) ?? route('dashboard'))
            ->with('success', 'Password changed successfully!');
    }
}
