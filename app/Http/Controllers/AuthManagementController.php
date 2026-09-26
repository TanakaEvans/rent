<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthManagementController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Display the auth management page (server-side search + pagination).
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::with(['employee:id,user_id,last_name', 'roles'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Auth/Management', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Reset a user's password to a temporary one (shown once to the admin).
     */
    public function resetUser(Request $request, User $user)
    {
        if ($blocker = $this->authService->manageBlocker($request->user(), $user)) {
            return back()->with('error', $blocker);
        }

        $temporary = $this->authService->resetPassword($user);

        return back()->with('success', "Password for {$user->name} reset. Temporary password: {$temporary} — share it privately; it is shown only once. The account is unlocked and the user must change the password at next sign-in.");
    }

    /**
     * Activate or deactivate a user.
     */
    public function toggleStatus(Request $request, User $user)
    {
        if ($blocker = $this->authService->toggleStatus($request->user(), $user)) {
            return back()->with('error', $blocker);
        }

        return back()->with('success', 'User account is now '.ucfirst($user->status).'.');
    }

    /**
     * Unlock an account locked by too many failed sign-in attempts.
     */
    public function unlockUser(User $user)
    {
        $user->update([
            'locked_at' => null,
            'failed_login_attempts' => 0,
        ]);

        return back()->with('success', 'User account unlocked successfully.');
    }
}
