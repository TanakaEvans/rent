<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account settings shared by every signed-in party (owner, admin, tenant,
 * contractor): display name and profile photo. Each action operates only on
 * the authenticated user, so nobody can change another account's details.
 */
class AccountController extends Controller
{
    /**
     * Disk the profile photos live on (public — served under /storage).
     */
    private const DISK = 'public';

    /**
     * The account settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('Account/Profile');
    }

    /**
     * Save the display name for the signed-in user.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update(['name' => $validated['name']]);

        return redirect()->route('account.profile')
            ->with('success', 'Profile saved.');
    }

    /**
     * Upload (or replace) the signed-in user's profile photo. The previous
     * file is deleted so replacements never orphan storage.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        $path = $request->file('avatar')->store('avatars/'.$user->id, self::DISK);

        $this->forgetPreviousAvatar($user->avatar_path);

        $user->update(['avatar_path' => $path]);

        return redirect()->route('account.profile')
            ->with('success', 'Profile photo updated.');
    }

    /**
     * Remove the signed-in user's profile photo: delete the file and null the
     * column so the monogram fallback shows again.
     */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        $this->forgetPreviousAvatar($user->avatar_path);

        $user->update(['avatar_path' => null]);

        return redirect()->route('account.profile')
            ->with('success', 'Profile photo removed.');
    }

    /**
     * Delete a stored avatar file if it exists.
     */
    private function forgetPreviousAvatar(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
