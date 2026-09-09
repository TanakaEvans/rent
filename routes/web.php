<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PublicPropertyController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\ViewingSlotController;
use App\Http\Controllers\ViewingRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureHasRole;

// Public marketplace landing page
Route::get('/', [HomeController::class, 'index'])->name('home');

// Public property detail page
Route::get('/properties/{id}', [PublicPropertyController::class, 'show'])
    ->name('property.show')
    ->whereNumber('id');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth', EnsurePasswordIsChanged::class, EnsureHasRole::class])->group(function () {
    // Main Dashboard Route
    Route::get('/dashboard', function () {
        $stats = [
            'total_users' => \App\Models\User::count(),
            'active_users' => \App\Models\User::where('status', 'active')->count(),
            'total_roles' => \App\Models\Role::count(),
            'total_employees' => \App\Models\Employee::count(),
            'total_branches' => \App\Models\Branch::count(),
        ];

        $recent_users = \App\Models\User::with('roles')
            ->latest()
            ->take(5)
            ->get();

        return inertia('Dashboard', [
            'stats' => $stats,
            'recent_users' => $recent_users,
        ]);
    })->name('dashboard')->defaults('description', 'Access main system dashboard');

    // In-app notifications (all authenticated roles)
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index')
        ->defaults('description', 'View my in-app notifications');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read')
        ->defaults('description', 'Mark a notification read and follow its link');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all')
        ->defaults('description', 'Mark all notifications as read');

    // Role-specific dashboards
    Route::middleware('role:Owner')->group(function () {
        Route::get('/owner', [OwnerDashboardController::class, 'index'])
            ->name('owner.dashboard')
            ->defaults('description', 'Access property owner dashboard');

        Route::prefix('owner/properties')->name('owner.properties.')->group(function () {
            Route::get('/', [PropertyController::class, 'index'])
                ->name('index')
                ->defaults('description', 'View my property listings');
            Route::get('create', [PropertyController::class, 'create'])
                ->name('create')
                ->defaults('description', 'Add a new property listing');
            Route::post('/', [PropertyController::class, 'store'])
                ->name('store')
                ->defaults('description', 'Save a new property listing');
            Route::get('{id}', [PropertyController::class, 'show'])
                ->name('show')
                ->defaults('description', 'View property listing details');
            Route::get('{id}/edit', [PropertyController::class, 'edit'])
                ->name('edit')
                ->defaults('description', 'Edit property listing');
            Route::put('{id}', [PropertyController::class, 'update'])
                ->name('update')
                ->defaults('description', 'Update property listing');
            Route::put('{id}/status', [PropertyController::class, 'updateStatus'])
                ->name('status')
                ->defaults('description', 'Change listing status');
            Route::delete('{id}', [PropertyController::class, 'destroy'])
                ->name('destroy')
                ->defaults('description', 'Delete property listing');
        });

        Route::get('/owner/enquiries', [EnquiryController::class, 'ownerIndex'])
            ->name('owner.enquiries.index')
            ->defaults('description', 'View enquiry inbox');
        Route::get('/owner/enquiries/{id}', [EnquiryController::class, 'ownerShow'])
            ->name('owner.enquiries.show')
            ->defaults('description', 'View an enquiry thread');
        Route::post('/owner/enquiries/{id}/reply', [EnquiryController::class, 'reply'])
            ->name('owner.enquiries.reply')
            ->defaults('description', 'Reply to an enquiry');
        Route::post('/owner/enquiries/{id}/close', [EnquiryController::class, 'close'])
            ->name('owner.enquiries.close')
            ->defaults('description', 'Close an enquiry thread');

        Route::prefix('owner/properties/{property}/slots')->name('owner.viewing-slots.')->group(function () {
            Route::get('/', [ViewingSlotController::class, 'index'])
                ->name('index')
                ->defaults('description', 'Manage viewing slots for a property');
            Route::post('/', [ViewingSlotController::class, 'store'])
                ->name('store')
                ->defaults('description', 'Add a viewing slot');
            Route::put('{slot}', [ViewingSlotController::class, 'update'])
                ->name('update')
                ->defaults('description', 'Update a viewing slot');
            Route::delete('{slot}', [ViewingSlotController::class, 'destroy'])
                ->name('destroy')
                ->defaults('description', 'Delete a viewing slot');
        });

        Route::get('/owner/viewings', [ViewingRequestController::class, 'indexOwner'])
            ->name('owner.viewings.index')
            ->defaults('description', 'View viewing requests');
        Route::post('/owner/viewings/{booking}/accept', [ViewingRequestController::class, 'accept'])
            ->name('owner.viewings.accept')
            ->defaults('description', 'Accept a viewing request and lock the slot');
        Route::post('/owner/viewings/{booking}/decline', [ViewingRequestController::class, 'decline'])
            ->name('owner.viewings.decline')
            ->defaults('description', 'Decline a viewing request');
        Route::post('/owner/viewings/{booking}/reschedule', [ViewingRequestController::class, 'reschedule'])
            ->name('owner.viewings.reschedule')
            ->defaults('description', 'Propose a new slot for a viewing');
        Route::post('/owner/viewings/{booking}/complete', [ViewingRequestController::class, 'complete'])
            ->name('owner.viewings.complete')
            ->defaults('description', 'Mark a viewing completed');
        Route::post('/owner/viewings/{booking}/no-show', [ViewingRequestController::class, 'noShow'])
            ->name('owner.viewings.no-show')
            ->defaults('description', 'Mark a tenant as a no-show');
        Route::post('/owner/viewings/{booking}/cancel', [ViewingRequestController::class, 'cancel'])
            ->name('owner.viewings.cancel')
            ->defaults('description', 'Cancel a viewing booking');

        Route::get('/owner/applications', [ApplicationController::class, 'ownerIndex'])
            ->name('owner.applications.index')
            ->defaults('description', 'Review rental applications');
        Route::post('/owner/applications/{application}/approve', [ApplicationController::class, 'approve'])
            ->name('owner.applications.approve')
            ->defaults('description', 'Approve a rental application');
        Route::post('/owner/applications/{application}/shortlist', [ApplicationController::class, 'toggleShortlist'])
            ->name('owner.applications.shortlist')
            ->defaults('description', 'Toggle an application shortlist');
        Route::post('/owner/applications/{application}/reject', [ApplicationController::class, 'reject'])
            ->name('owner.applications.reject')
            ->defaults('description', 'Reject a rental application');

        Route::post('/owner/applications/{application}/lease', [LeaseController::class, 'createFromApplication'])
            ->name('owner.applications.lease')
            ->defaults('description', 'Generate a lease from an approved application');
        Route::get('/owner/leases', [LeaseController::class, 'ownerIndex'])
            ->name('owner.leases.index')
            ->defaults('description', 'View leases for your properties');
    });

    Route::middleware('role:Tenant')->group(function () {
        Route::get('/tenant', [TenantDashboardController::class, 'index'])
            ->name('tenant.dashboard')
            ->defaults('description', 'Access tenant dashboard');

        Route::get('/tenant/favourites', [FavouriteController::class, 'index'])
            ->name('tenant.favourites.index')
            ->defaults('description', 'View saved favourites');

        Route::post('/tenant/favourites/{property}', [FavouriteController::class, 'toggle'])
            ->name('tenant.favourites.toggle')
            ->defaults('description', 'Toggle a property favourite');

        Route::get('/tenant/enquiries', [EnquiryController::class, 'tenantIndex'])
            ->name('tenant.enquiries.index')
            ->defaults('description', 'View my enquiry threads');
        Route::post('/tenant/enquiries/{property}', [EnquiryController::class, 'store'])
            ->name('tenant.enquiries.store')
            ->defaults('description', 'Send an enquiry about a property');

        Route::get('/tenant/viewings', [ViewingRequestController::class, 'indexTenant'])
            ->name('tenant.viewings.index')
            ->defaults('description', 'View my viewing bookings');
        Route::post('/tenant/viewings', [ViewingRequestController::class, 'store'])
            ->name('tenant.viewings.store')
            ->defaults('description', 'Request a viewing on a slot');
        Route::post('/tenant/viewings/{booking}/confirm', [ViewingRequestController::class, 'confirm'])
            ->name('tenant.viewings.confirm')
            ->defaults('description', 'Confirm a rescheduled slot');
        Route::post('/tenant/viewings/{booking}/cancel', [ViewingRequestController::class, 'cancel'])
            ->name('tenant.viewings.cancel')
            ->defaults('description', 'Cancel my viewing booking');

        Route::get('/tenant/applications', [ApplicationController::class, 'tenantIndex'])
            ->name('tenant.applications.index')
            ->defaults('description', 'View my rental applications');
        Route::post('/tenant/applications/{property}', [ApplicationController::class, 'store'])
            ->name('tenant.applications.store')
            ->defaults('description', 'Submit a rental application');

        Route::get('/tenant/leases', [LeaseController::class, 'tenantIndex'])
            ->name('tenant.leases.index')
            ->defaults('description', 'View my leases');
    });

    // Force Change Password Routes
    Route::get('password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::put('password/change', [ChangePasswordController::class, 'update'])->name('password.update');

    // User Management Routes (Admin only)
    Route::prefix('auth')->name('auth.')->middleware('admin')->group(function () {
        // Users CRUD
        Route::get('users', [UserController::class, 'index'])
            ->name('users.index')
            ->defaults('description', 'View and manage all system users');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->name('users.show')
            ->defaults('description', 'View user details');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])
            ->name('users.edit')
            ->defaults('description', 'Edit user information');
        Route::patch('users/{user}', [UserController::class, 'update'])
            ->name('users.update')
            ->defaults('description', 'Update user information');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy')
            ->defaults('description', 'Delete user account');
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status')
            ->defaults('description', 'Activate or deactivate user');

        // Role Bulk Operations & Reports
        Route::get('roles/bulk-assign', [RoleController::class, 'bulkAssign'])->name('roles.bulk-assign');
        Route::post('roles/bulk-assign', [RoleController::class, 'storeBulkAssign'])->name('roles.bulk-assign.store');
        Route::get('roles/bulk-remove', [RoleController::class, 'bulkRemove'])->name('roles.bulk-remove');
        Route::post('roles/bulk-remove', [RoleController::class, 'storeBulkRemove'])->name('roles.bulk-remove.store');
        Route::get('roles/users-report', [RoleController::class, 'usersReport'])->name('roles.users-report');

        // Roles CRUD
        Route::get('roles', [RoleController::class, 'index'])
            ->name('roles.index')
            ->defaults('description', 'View and manage user roles');
        Route::get('roles/create', [RoleController::class, 'create'])
            ->name('roles.create')
            ->defaults('description', 'Create new user role');
        Route::post('roles', [RoleController::class, 'store'])
            ->name('roles.store')
            ->defaults('description', 'Save new role to database');
        Route::get('roles/{role}', [RoleController::class, 'show'])
            ->name('roles.show')
            ->defaults('description', 'View role details and permissions');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->name('roles.edit')
            ->defaults('description', 'Edit role information');
        Route::patch('roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update')
            ->defaults('description', 'Update role information');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy')
            ->defaults('description', 'Delete user role');
    });

// Auth Management (Admin only)
Route::prefix('auth')->name('auth.')->middleware('admin')->group(function () {
    Route::get('management', [\App\Http\Controllers\AuthManagementController::class, 'index'])->name('management');
    Route::post('management/{user}/reset', [\App\Http\Controllers\AuthManagementController::class, 'resetUser'])->name('management.reset');
    Route::post('management/{user}/unlock', [\App\Http\Controllers\AuthManagementController::class, 'unlockUser'])->name('management.unlock');
    Route::patch('management/{user}/toggle-status', [\App\Http\Controllers\AuthManagementController::class, 'toggleStatus'])->name('management.toggle-status');
});

    // Admin Routes (Company, Branches, Departments, Employees)
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        // Admin Dashboard
        Route::get('dashboard', function () {
            $stats = [
                'total_users' => \App\Models\User::count(),
                'active_users' => \App\Models\User::where('status', 'active')->count(),
                'total_roles' => \App\Models\Role::count(),
                'total_employees' => \App\Models\Employee::count(),
                'total_branches' => \App\Models\Branch::count(),
                'total_departments' => \App\Models\Department::count(),
                'total_properties' => \App\Models\Property::count(),
                'listed_properties' => \App\Models\Property::where('status', 'available')->count(),
                'verified_properties' => \App\Models\Property::where('verified', true)->count(),
                'total_applications' => \App\Models\RentalApplication::count(),
            ];

            return inertia('Admin/Dashboard', [
                'stats' => $stats,
            ]);
        })->name('dashboard')->defaults('description', 'Access system administration dashboard');

        // Sections
        Route::resource('sections', \App\Http\Controllers\SectionController::class);

        // Company Details
        Route::get('company', [CompanyController::class, 'index'])
            ->name('company.index')
            ->defaults('description', 'View and manage company details');
        Route::post('company', [CompanyController::class, 'store'])
            ->name('company.store')
            ->defaults('description', 'Save company details');
        Route::post('company/logo', [CompanyController::class, 'uploadLogo'])
            ->name('company.logo')
            ->defaults('description', 'Upload company logo');

        // Branches CRUD
        Route::get('branches', [BranchController::class, 'index'])
            ->name('branches.index')
            ->defaults('description', 'View and manage company branches');
        Route::get('branches/create', [BranchController::class, 'create'])
            ->name('branches.create')
            ->defaults('description', 'Create new branch');
        Route::post('branches', [BranchController::class, 'store'])
            ->name('branches.store')
            ->defaults('description', 'Save new branch');
        Route::get('branches/{branch}', [BranchController::class, 'show'])
            ->name('branches.show')
            ->defaults('description', 'View branch details');
        Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])
            ->name('branches.edit')
            ->defaults('description', 'Edit branch information');
        Route::patch('branches/{branch}', [BranchController::class, 'update'])
            ->name('branches.update')
            ->defaults('description', 'Update branch information');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])
            ->name('branches.destroy')
            ->defaults('description', 'Delete branch');
        Route::patch('branches/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])
            ->name('branches.toggle-status')
            ->defaults('description', 'Activate or deactivate branch');

        // Departments CRUD
        Route::get('departments', [DepartmentController::class, 'index'])
            ->name('departments.index')
            ->defaults('description', 'View and manage departments');
        Route::get('departments/create', [DepartmentController::class, 'create'])
            ->name('departments.create')
            ->defaults('description', 'Create new department');
        Route::post('departments', [DepartmentController::class, 'store'])
            ->name('departments.store')
            ->defaults('description', 'Save new department');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])
            ->name('departments.show')
            ->defaults('description', 'View department details');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])
            ->name('departments.edit')
            ->defaults('description', 'Edit department information');
        Route::patch('departments/{department}', [DepartmentController::class, 'update'])
            ->name('departments.update')
            ->defaults('description', 'Update department information');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
            ->name('departments.destroy')
            ->defaults('description', 'Delete department');
        Route::patch('departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])
            ->name('departments.toggle-status')
            ->defaults('description', 'Activate or deactivate department');

        // Employees CRUD
        Route::get('employees', [EmployeeController::class, 'index'])
            ->name('employees.index')
            ->defaults('description', 'View and manage employees');
        Route::get('employees/create', [EmployeeController::class, 'create'])
            ->name('employees.create')
            ->defaults('description', 'Add new employee');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->name('employees.store')
            ->defaults('description', 'Save new employee');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])
            ->name('employees.show')
            ->defaults('description', 'View employee details');
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])
            ->name('employees.edit')
            ->defaults('description', 'Edit employee information');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update'])
            ->name('employees.update')
            ->defaults('description', 'Update employee information');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
            ->name('employees.destroy')
            ->defaults('description', 'Delete employee');
        Route::patch('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])
            ->name('employees.toggle-status')
            ->defaults('description', 'Activate or deactivate employee');
        Route::get('employees/{employee}/create-user', [EmployeeController::class, 'createUserAccount'])
            ->name('employees.create-user')
            ->defaults('description', 'Create user account for employee');
        Route::post('employees/{employee}/create-user', [EmployeeController::class, 'storeUserAccount'])
            ->name('employees.store-user')
            ->defaults('description', 'Save user account for employee');
    });
});