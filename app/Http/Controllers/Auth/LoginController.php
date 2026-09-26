<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $login = $request->login;

        // Try to find user by email or username
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        // A locked account stays blocked until an administrator unlocks it
        if ($user && $user->locked_at) {
            return back()->with('error', AuthService::LOCKED_MESSAGE);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // If user exists, increment failed attempts
            if ($user) {
                $user->increment('failed_login_attempts');

                if ($user->failed_login_attempts >= 7) {
                    $user->update(['locked_at' => now()]);

                    return back()->with('error', AuthService::LOCKED_MESSAGE);
                }
            }

            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        // Deactivated accounts are only revealed to someone who knows the password
        if ($user->status === 'inactive') {
            return back()->with('error', AuthService::DEACTIVATED_MESSAGE);
        }

        // Reset failed login attempts on successful login
        $user->update([
            'failed_login_attempts' => 0,
        ]);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended($this->authService->landingUrlFor($user) ?? route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
