<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\SystemModule;
use App\Models\SystemRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ExtractRoutesCommand extends Command
{
    protected $signature = 'routes:extract';

    protected $description = 'Extract route names and save them in the database for role-based access control.';

    public function handle()
    {
        $this->info('Starting route extraction...');

        // Get all routes from the application but exclude API routes and debugbar
        $routes = collect(Route::getRoutes())->reject(function ($route) {
            return Str::contains($route->uri(), 'api') ||
                   Str::contains($route->uri(), '_debugbar') ||
                   Str::contains($route->uri(), 'ignition') ||
                   Str::contains($route->uri(), 'sanctum');
        });

        // Filter routes to get only the routes with names
        $routes = $routes->filter(function ($route) {
            return $route->getName();
        });

        DB::beginTransaction();

        try {
            // Get enabled modules
            $modules = SystemModule::where('status', 'Enabled')->get();
            $modulePrefixes = $modules->pluck('prefix')->toArray();

            if (empty($modulePrefixes)) {
                $this->warn('No enabled modules found. Creating default modules...');
                $this->createDefaultModules();
                $modules = SystemModule::where('status', 'Enabled')->get();
                $modulePrefixes = $modules->pluck('prefix')->toArray();
            }

            // Filter routes that match module prefixes
            $filteredRoutes = $routes->filter(function ($route) use ($modulePrefixes) {
                foreach ($modulePrefixes as $prefix) {
                    if (Str::contains($route->uri(), $prefix)) {
                        return true;
                    }
                }

                return false;
            });

            $routeCount = 0;
            $moduleStats = [];

            foreach ($filteredRoutes as $route) {
                $routeName = $route->uri();
                $description = $route->defaults['description'] ?? $this->generateDescription($route->getName(), $route->uri());

                // Find the matching module
                $moduleId = null;
                foreach ($modules as $module) {
                    if (Str::contains($routeName, $module->prefix)) {
                        $moduleId = $module->id;
                        $moduleStats[$module->name] = ($moduleStats[$module->name] ?? 0) + 1;
                        break;
                    }
                }

                // Create or update the route
                SystemRoute::updateOrCreate(
                    ['name' => $routeName],
                    [
                        'system_module_id' => $moduleId,
                        'uri' => $route->uri(),
                        'description' => $description,
                        'status' => 'active',
                    ]
                );

                $routeCount++;
            }

            // Create or get Superuser role
            $superuserRole = Role::firstOrCreate(
                ['name' => 'Superuser'],
                ['description' => 'Has access to all system functions and routes']
            );

            // Assign all routes to superuser role
            $allRouteIds = SystemRoute::pluck('id')->toArray();
            $superuserRole->systemRoutes()->syncWithoutDetaching($allRouteIds);

            // Create default canteen roles
            $this->createDefaultRoles();

            DB::commit();

            $this->info('Routes extracted successfully!');
            $this->info("Total routes processed: {$routeCount}");

            $this->newLine();
            $this->info('Routes by module:');
            foreach ($moduleStats as $module => $count) {
                $this->line("  📁 {$module}: {$count} routes");
            }

            $this->newLine();
            $this->info('Superuser role updated with all route permissions.');

        } catch (\Exception $e) {
            DB::rollback();
            $this->error('Error extracting routes: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function createDefaultModules()
    {
        $modules = [
            ['name' => 'Dashboard', 'prefix' => 'dashboard', 'icon' => 'mdi-view-dashboard', 'order' => 1],
            ['name' => 'System Settings', 'prefix' => 'admin', 'icon' => 'mdi-cog', 'order' => 2],
            ['name' => 'User Management', 'prefix' => 'auth/users', 'icon' => 'mdi-account-multiple', 'order' => 3],
            ['name' => 'Role Management', 'prefix' => 'auth/roles', 'icon' => 'mdi-account-star', 'order' => 4],
            ['name' => 'Company Settings', 'prefix' => 'admin/company', 'icon' => 'mdi-office-building', 'order' => 5],
            ['name' => 'Branch Management', 'prefix' => 'admin/branches', 'icon' => 'mdi-home-city', 'order' => 6],
            ['name' => 'Department Management', 'prefix' => 'admin/departments', 'icon' => 'mdi-account-group', 'order' => 7],
            ['name' => 'Employee Management', 'prefix' => 'admin/employees', 'icon' => 'mdi-badge-account', 'order' => 8],
            ['name' => 'Canteen Operations', 'prefix' => 'canteen', 'icon' => 'mdi-silverware-fork-knife', 'order' => 9],
        ];

        foreach ($modules as $module) {
            SystemModule::firstOrCreate(
                ['prefix' => $module['prefix']],
                array_merge($module, ['status' => 'Enabled'])
            );
        }
    }

    private function createDefaultRoles()
    {
        $roles = [
            ['name' => 'Admin', 'description' => 'Administrative access to system settings and user management'],
            ['name' => 'Manager', 'description' => 'Canteen manager with full operational access'],
            ['name' => 'Cashier', 'description' => 'Point of sale and order processing access'],
            ['name' => 'Kitchen Staff', 'description' => 'Order preparation and kitchen management'],
            ['name' => 'Inventory Clerk', 'description' => 'Stock and inventory management access'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }

    /**
     * Determine the module for a route
     */
    private function determineModule($route): string
    {
        $uri = $route->uri();
        $name = $route->getName();

        // Define module mapping based on URI patterns and route names
        $modulePatterns = [
            'Authentication & User Management' => ['auth/', 'auth.', 'login', 'logout', 'register'],
            'Dashboard & Analytics' => ['dashboard', 'analytics', 'reports'],
            'Academic Management' => ['academic/', 'courses/', 'subjects/', 'grades/', 'academic.'],
            'Student Portal' => ['students/', 'student.', 'enrollment'],
            'Teacher Portal' => ['teachers/', 'teacher.', 'teaching'],
            'Parent Portal' => ['parents/', 'parent.', 'family'],
            'Staff Management' => ['staff/', 'staff.', 'hr/', 'payroll'],
            'Financial Management' => ['finance/', 'billing/', 'fees/', 'payments'],
            'Communication' => ['messages/', 'notifications/', 'announcements'],
            'System Administration' => ['admin/', 'system/', 'settings/', 'config'],
            'General' => [''], // Default fallback
        ];

        foreach ($modulePatterns as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::contains($uri, $pattern) || Str::contains($name, $pattern)) {
                    return $module;
                }
            }
        }

        return 'General';
    }

    /**
     * Get priority for route ordering (lower number = higher priority)
     */
    private function getRoutePriority(string $routeName): int
    {
        $priorityMap = [
            'dashboard' => 1,
            'index' => 2,
            'create' => 3,
            'store' => 4,
            'show' => 5,
            'edit' => 6,
            'update' => 7,
            'destroy' => 9,
            'toggle-status' => 8,
        ];

        $parts = explode('.', $routeName);
        $action = end($parts);

        return $priorityMap[$action] ?? 5;
    }

    /**
     * Generate a human-readable description for a route name
     */
    private function generateDescription(string $routeName, string $uri = ''): string
    {
        // Convert route name to readable description
        $parts = explode('.', $routeName);
        $action = end($parts);
        $resource = count($parts) > 1 ? $parts[count($parts) - 2] : 'resource';

        // Clean up resource name
        $resource = str_replace(['_', '-'], ' ', $resource);
        $resourceSingular = Str::singular($resource);
        $resourcePlural = Str::plural($resource);

        $actionDescriptions = [
            'index' => "View and manage all {$resourcePlural}",
            'create' => "Create new {$resourceSingular}",
            'store' => "Save new {$resourceSingular} to database",
            'show' => "View {$resourceSingular} details and information",
            'edit' => "Edit and modify {$resourceSingular}",
            'update' => "Update {$resourceSingular} information",
            'destroy' => "Delete {$resourceSingular} permanently",
            'dashboard' => "Access {$resource} dashboard and overview",
            'toggle-status' => "Activate or deactivate {$resourceSingular}",
            'login' => 'Authenticate and log into the system',
            'logout' => 'Log out of the system',
            'register' => 'Register new user account',
        ];

        // Special cases for common routes
        if ($routeName === 'dashboard') {
            return 'Access main system dashboard';
        }

        if (Str::contains($routeName, 'auth.dashboard')) {
            return 'Access authentication management dashboard';
        }

        if (Str::contains($routeName, 'roles')) {
            $actionDescriptions = array_merge($actionDescriptions, [
                'index' => 'View and manage system roles and permissions',
                'create' => 'Create new system role',
                'show' => 'View role details and assigned permissions',
                'edit' => 'Edit role name and permissions',
            ]);
        }

        if (Str::contains($routeName, 'users')) {
            $actionDescriptions = array_merge($actionDescriptions, [
                'index' => 'View and manage all user accounts',
                'create' => 'Create new user account',
                'show' => 'View user profile and account details',
                'edit' => 'Edit user information and roles',
            ]);
        }

        return $actionDescriptions[$action] ?? 'Access '.str_replace(['-', '_', '.'], ' ', $routeName);
    }
}
