<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class AuthManagementController extends Controller
{
    /**
     * Display the auth management page.
     */
    public function index()
    {
        $users = User::with(['employee', 'roles'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Auth/Management', [
            'users' => $users,
        ]);
    }

    /**
     * Reset user password to default.
     */
    public function resetUser(User $user)
    {
        try {
            if (! $user->employee) {
                return back()->with('error', 'User is not linked to an employee. Cannot generate default password.');
            }

            // Default password: lowercase last_name
            if (! $user->employee->last_name) {
                return back()->with('error', 'Employee data incomplete (missing last name).');
            }

            $defaultPassword = strtolower($user->employee->last_name);
            $user->update([
                'password' => bcrypt($defaultPassword),
                'password_changed_at' => null, // Force change on next login
                'password_expires_at' => now()->addMonths(5),
                'failed_login_attempts' => 0,
                'locked_at' => null, // Unlock account
            ]);

            return back()->with('success', "Password reset successfully. Default password is: {$defaultPassword}. The user has been unlocked and must change their password on login.");
        } catch (\Exception $e) {
            return back()->with('error', 'Error resetting password: '.$e->getMessage());
        }
    }

    /**
     * Lock/Unlock user (Toggle Status).
     */
    public function toggleStatus(User $user)
    {
        try {
            $user->status = $user->status === 'active' ? 'inactive' : 'active';
            $user->save();

            $status = ucfirst($user->status);

            return back()->with('success', "User account is now {$status}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Error updating status: '.$e->getMessage());
        }
    }

    /**
     * Unlock a locked user account.
     */
    public function unlockUser(User $user)
    {
        try {
            $user->update([
                'locked_at' => null,
                'failed_login_attempts' => 0,
            ]);

            return back()->with('success', 'User account unlocked successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error unlocking user: '.$e->getMessage());
        }
    }
}
