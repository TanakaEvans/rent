<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Display a listing of roles.
     */
    public function index(Request $request)
    {
        $query = Role::withCount(['users', 'users as active_users_count' => function ($query) {
            $query->where('status', 'active');
        }, 'systemRoutes']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->orderBy('name')->paginate(15)->withQueryString();

        return inertia('Roles/Index', [
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        // Get all routes grouped by module
        $modules = SystemModule::with('routes')->where('status', 'Enabled')->orderBy('order')->get();

        $routesByModule = $modules->mapWithKeys(function ($module) {
            return [$module->name => $module->routes];
        });

        return inertia('Roles/Create', [
            'routesByModule' => $routesByModule,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:auth_roles'],
            'description' => ['nullable', 'string', 'max:255'],
            'route_ids' => ['array'],
            'route_ids.*' => ['exists:system_routes,id'],
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Assign route permissions if provided
        if ($request->route_ids) {
            $role->systemRoutes()->sync($request->route_ids);
        }

        return redirect()->route('auth.roles.index')
            ->with('success', 'Role created successfully with '.count($request->route_ids ?? []).' permissions assigned.');
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load(['users' => function ($query) {
            $query->with('roles')->limit(10);
        }, 'systemRoutes.module']);

        // Organize routes by module
        $routesByModule = $role->systemRoutes->groupBy(function ($route) {
            return $route->module?->name ?? 'General';
        });

        return inertia('Roles/Show', [
            'role' => $role,
            'routesByModule' => $routesByModule,
        ]);
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role)
    {
        $role->load('systemRoutes');
        $modules = SystemModule::with('routes')->where('status', 'Enabled')->orderBy('order')->get();
        $assignedRouteIds = $role->systemRoutes->pluck('id')->toArray();

        $routesByModule = $modules->mapWithKeys(function ($module) {
            return [$module->name => $module->routes];
        });

        return inertia('Roles/Edit', [
            'role' => $role,
            'routesByModule' => $routesByModule,
            'assignedRouteIds' => $assignedRouteIds,
            'isSystemRole' => in_array($role->name, AuthService::SYSTEM_ROLES, true),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:auth_roles,name,'.$role->id],
            'description' => ['nullable', 'string', 'max:255'],
            'route_ids' => ['array'],
            'route_ids.*' => ['exists:system_routes,id'],
        ]);

        if ($request->name !== $role->name && in_array($role->name, AuthService::SYSTEM_ROLES, true)) {
            throw ValidationException::withMessages([
                'name' => "The {$role->name} role is a system role and cannot be renamed.",
            ]);
        }

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Update route permissions
        if ($request->has('route_ids')) {
            $role->systemRoutes()->sync($request->route_ids ?? []);
        }

        return redirect()->route('auth.roles.index')
            ->with('success', 'Role updated successfully with '.count($request->route_ids ?? []).' permissions.');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        // System roles are referenced by name in code and can never be deleted
        if (in_array($role->name, AuthService::SYSTEM_ROLES, true)) {
            return redirect()->route('auth.roles.index')
                ->with('error', "Cannot delete the {$role->name} role; it is a system role.");
        }

        // Prevent deletion of roles that have users
        if ($role->hasUsers()) {
            return redirect()->route('auth.roles.index')
                ->with('error', 'Cannot delete role that has assigned users. Please reassign users first.');
        }

        $role->delete();

        return redirect()->route('auth.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Show the bulk assign roles form.
     */
    public function bulkAssign()
    {
        $roles = Role::orderBy('name')->get();
        $users = User::with('roles')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return inertia('Roles/BulkAssign', [
            'roles' => $roles,
            'users' => $users,
        ]);
    }

    /**
     * Store bulk role assignments.
     */
    public function storeBulkAssign(Request $request)
    {
        $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:auth_roles,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:auth_users,id'],
        ]);

        $assignedCount = $this->authService->bulkAssignRoles($request->user(), $request->user_ids, $request->role_ids);

        return redirect()->route('auth.roles.index')
            ->with('success', "Roles assigned successfully to {$assignedCount} users.");
    }

    /**
     * Show the bulk remove roles form.
     */
    public function bulkRemove()
    {
        $roles = Role::orderBy('name')->get();
        // Only get users who actually have roles
        $users = User::with('roles')
            ->has('roles')
            ->orderBy('name')
            ->get();

        return inertia('Roles/BulkRemove', [
            'roles' => $roles,
            'users' => $users,
        ]);
    }

    /**
     * Store bulk role removals.
     */
    public function storeBulkRemove(Request $request)
    {
        $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:auth_roles,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:auth_users,id'],
        ]);

        $processedCount = $this->authService->bulkRemoveRoles($request->user(), $request->user_ids, $request->role_ids);

        return redirect()->route('auth.roles.index')
            ->with('success', "Roles removed successfully from {$processedCount} users.");
    }

    /**
     * Display users with roles report.
     */
    public function usersReport()
    {
        $users = User::with('roles')->paginate(20);
        $roles = Role::all();

        return inertia('Roles/UsersReport', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }
}
