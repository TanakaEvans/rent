<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ChangePasswordController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/ChangePassword');
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

        $request->user()->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'password_expires_at' => now()->addMonths(5),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Password changed successfully!');
    }
}
