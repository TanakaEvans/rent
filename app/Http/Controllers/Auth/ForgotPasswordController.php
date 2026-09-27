<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Step 1 of the self-service reset: ask for the account email and mail a
 * signed reset link. Per product decision the response is explicit — an
 * unregistered email is reported back rather than hidden. (This trades away
 * user-enumeration resistance for clearer feedback.)
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

        // sendResetLink mails the ResetPassword notification and throttles per
        // the broker config; the status tells us whether the email matched.
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'A password reset link is on its way to your inbox.');
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => ['A reset link was just sent — please wait a moment before requesting another.'],
            ]);
        }

        // INVALID_USER (and any other failure): tell the person plainly.
        throw ValidationException::withMessages([
            'email' => ["That email isn't linked to any ZimRent account. Check the spelling or create an account."],
        ]);
    }
}
