<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $query = User::with('roles');

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by role
            if ($request->filled('role')) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            $users = $query->paginate(15)->withQueryString();
            $roles = Role::all();

            return inertia('Admin/Users/Index', [
                'users' => $users,
                'roles' => $roles,
                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'role' => $request->role,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Users index error: '.$e->getMessage());

            return inertia('Admin/Users/Index', [
                'users' => collect([]),
                'roles' => collect([]),
                'filters' => [
                    'search' => '',
                    'status' => '',
                    'role' => '',
                ],
                'error' => 'Error loading users: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load('roles');

        return inertia('Admin/Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $user->load('roles');

        return inertia('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('auth_users', 'email')->ignore($user->id)],
            'username' => ['required', 'string', 'max:100', Rule::unique('auth_users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'status' => ['required', 'in:active,inactive'],
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'exists:auth_roles,id'],
        ]);

        $this->authService->updateAccount($request->user(), $user, $validated);

        return redirect()->route('auth.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user (only accounts with no tenancy or money history).
     */
    public function destroy(Request $request, User $user)
    {
        if ($blocker = $this->authService->deletionBlocker($request->user(), $user)) {
            return back()->with('error', $blocker);
        }

        $user->delete();

        return redirect()->route('auth.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(Request $request, User $user)
    {
        if ($blocker = $this->authService->toggleStatus($request->user(), $user)) {
            return back()->with('error', $blocker);
        }

        return back()->with('success', "User status changed to {$user->status}.");
    }
}
