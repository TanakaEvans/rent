<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;

        // Try to find user by email or username
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        // Check if user exists and is locked
        if ($user && $user->locked_at) {
            return back()->with('error', 'Your account has been locked due to too many failed login attempts. Please contact an administrator.');
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // If user exists, increment failed attempts
            if ($user) {
                $user->increment('failed_login_attempts');

                if ($user->failed_login_attempts >= 7) {
                    $user->update(['locked_at' => now()]);

                    return back()->with('error', 'Your account has been locked due to too many failed login attempts. Please contact an administrator.');
                }
            }

            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        // Reset failed login attempts on successful login
        $user->update([
            'failed_login_attempts' => 0,
            'locked_at' => null, // Ensure locked_at is cleared just in case
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($this->landingFor($user));
    }

    /**
     * Determine the landing page for a user based on their role.
     */
    protected function landingFor(User $user): string
    {
        if ($user->hasAnyRole(['Admin', 'Superuser'])) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('Owner')) {
            return route('owner.dashboard');
        }

        if ($user->hasRole('Tenant')) {
            return route('tenant.dashboard');
        }

        if ($user->hasRole('Contractor')) {
            return route('contractor.maintenance.index');
        }

        return route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
