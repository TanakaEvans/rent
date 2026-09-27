<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

/**
 * Step 1 of the self-service reset: ask for the account email and mail a
 * signed reset link. The response is deliberately generic so the endpoint
 * never reveals whether an email is registered (no user enumeration).
 */
class ForgotPasswordController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ]);

        // Password::sendResetLink throttles per the broker config and mails the
        // ResetPassword notification when the email matches an account. We
        // ignore the specific status and always return the same message.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email is registered, a password reset link is on its way.');
    }
}
