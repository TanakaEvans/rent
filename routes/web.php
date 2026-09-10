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
use App\Http\Controllers\AdminPlansController;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\OwnerSubscriptionController;
use App\Http\Controllers\RentController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\MarketplaceReportController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminMarketplaceAnalyticsController;
use App\Http\Controllers\OwnerAnalyticsController;
use App\Http\Controllers\AdPlacementController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ContractorController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureHasRole;

// Public marketplace landing page
Route::get('/', [HomeController::class, 'index'])->name('home');

// Public marketplace autocomplete
Route::get('/search/suggestions', [HomeController::class, 'suggest'])->name('search.suggestions');

// Public report listing (guests may report; auth optional)
Route::post('/properties/{id}/report', [MarketplaceReportController::class, 'store'])
    ->name('property.report')
    ->whereNumber('id');

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

    // Agreement documents (M20-lite) - parties on the lease or admin
    Route::get('/documents/{document}', [\App\Http\Controllers\DocumentController::class, 'show'])
        ->name('documents.show')
        ->defaults('description', 'View a stored agreement document');
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download'])
        ->name('documents.download')
        ->defaults('description', 'Download a stored agreement document');

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
        Route::post('/owner/leases/{lease}/send', [LeaseController::class, 'sendForSignature'])
            ->name('owner.leases.send')
            ->defaults('description', 'Send a draft lease to the tenant for signature');
        Route::post('/owner/leases/{lease}/sign', [LeaseController::class, 'sign'])
            ->name('owner.leases.sign')
            ->defaults('description', 'Sign a lease as the property owner');
        Route::post('/owner/leases/{lease}/renew', [LeaseController::class, 'renew'])
            ->name('owner.leases.renew')
            ->defaults('description', 'Create a renewal lease from an active lease');

        Route::get('/owner/documents', [\App\Http\Controllers\DocumentController::class, 'ownerIndex'])
            ->name('owner.documents.index')
            ->defaults('description', 'View agreement documents for your properties');

        Route::get('/owner/subscriptions', [OwnerSubscriptionController::class, 'index'])
            ->name('owner.subscriptions.index')
            ->defaults('description', 'View and manage my subscription');
        Route::post('/owner/subscriptions', [OwnerSubscriptionController::class, 'subscribe'])
            ->name('owner.subscriptions.subscribe')
            ->defaults('description', 'Subscribe to a plan');

        Route::get('/owner/rent', [RentController::class, 'ownerIndex'])
            ->name('owner.rent.index')
            ->defaults('description', 'View rent schedules and invoices for your properties');

        // Listing lifecycle + marketplace analytics (Marketplace §41/§43)
        Route::post('/owner/properties/{property}/renew', [PropertyController::class, 'renew'])
            ->name('owner.properties.renew')
            ->whereNumber('property')
            ->defaults('description', 'Renew a listing for another validity period');

        Route::get('/owner/analytics', [OwnerAnalyticsController::class, 'index'])
            ->name('owner.analytics.index')
            ->defaults('description', 'View marketplace performance for my properties');

        // Featured & advertising (M13, Wave 4 slice 6)
        Route::get('/owner/advertising', [AdPlacementController::class, 'ownerIndex'])
            ->name('owner.advertising.index')
            ->defaults('description', 'Book and track listing promotions');
        Route::post('/owner/advertising', [AdPlacementController::class, 'store'])
            ->name('owner.advertising.store')
            ->defaults('description', 'Book a promotion for one of my listings');

        // Maintenance report & triage (M10, Wave 5 slice 1)
        Route::get('/owner/maintenance', [MaintenanceController::class, 'ownerIndex'])
            ->name('owner.maintenance.index')
            ->defaults('description', 'Triage maintenance requests on my properties');
        // Maintenance assignment (M11, Wave 5 slice 2)
        Route::post('/owner/maintenance/{maintenanceRequest}/assign', [MaintenanceController::class, 'ownerAssign'])
            ->name('owner.maintenance.assign')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Assign a verified contractor to a maintenance request');
        // Maintenance track & close (Wave 5 slice 3)
        Route::post('/owner/maintenance/{maintenanceRequest}/close', [MaintenanceController::class, 'ownerClose'])
            ->name('owner.maintenance.close')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Close a repaired maintenance request');
        // Contractor ratings (M11, Wave 5 slice 4)
        Route::post('/owner/maintenance/{maintenanceRequest}/rate', [MaintenanceController::class, 'ownerRate'])
            ->name('owner.maintenance.rate')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Rate the contractor behind a closed job');
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

        Route::post('/tenant/leases/{lease}/sign', [LeaseController::class, 'sign'])
            ->name('tenant.leases.sign')
            ->defaults('description', 'Sign a lease as the tenant');

        Route::get('/tenant/documents', [\App\Http\Controllers\DocumentController::class, 'tenantIndex'])
            ->name('tenant.documents.index')
            ->defaults('description', 'View my agreement documents');

        Route::get('/tenant/rent', [RentController::class, 'tenantIndex'])
            ->name('tenant.rent.index')
            ->defaults('description', 'View my rent invoices');
        Route::post('/tenant/rent/{invoice}/pay', [\App\Http\Controllers\PaymentController::class, 'tenantStore'])
            ->name('tenant.rent.pay')
            ->defaults('description', 'Pay a rent invoice');
        Route::get('/tenant/rent/payments/{payment}/receipt', [\App\Http\Controllers\PaymentController::class, 'tenantReceipt'])
            ->name('tenant.rent.receipt')
            ->defaults('description', 'Download a rent receipt');

        // Saved marketplace searches + report history (Marketplace §47)
        Route::get('/tenant/saved-searches', [SavedSearchController::class, 'index'])
            ->name('tenant.saved-searches.index')
            ->defaults('description', 'View my saved marketplace searches');
        Route::post('/tenant/saved-searches', [SavedSearchController::class, 'store'])
            ->name('tenant.saved-searches.store')
            ->defaults('description', 'Save the current marketplace filters');
        Route::put('/tenant/saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])
            ->name('tenant.saved-searches.update')
            ->defaults('description', 'Update a saved marketplace search');
        Route::delete('/tenant/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])
            ->name('tenant.saved-searches.destroy')
            ->defaults('description', 'Delete a saved marketplace search');

        Route::get('/tenant/reports', [MarketplaceReportController::class, 'index'])
            ->name('tenant.reports.index')
            ->defaults('description', 'View my marketplace reports');

        // Maintenance report & triage (M10, Wave 5 slice 1)
        Route::get('/tenant/maintenance', [MaintenanceController::class, 'tenantIndex'])
            ->name('tenant.maintenance.index')
            ->defaults('description', 'Report and track maintenance issues');
        Route::post('/tenant/maintenance', [MaintenanceController::class, 'store'])
            ->name('tenant.maintenance.store')
            ->defaults('description', 'Submit a maintenance request');
        // Maintenance track & close (Wave 5 slice 3)
        Route::post('/tenant/maintenance/{maintenanceRequest}/confirm', [MaintenanceController::class, 'tenantConfirm'])
            ->name('tenant.maintenance.confirm')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Confirm a completed maintenance fix');
    });

    // Contractor portal (M11, Wave 5 slice 2) — registered tradespeople scope
    Route::middleware('role:Contractor')->group(function () {
        // Contractor is not a platform subscriber; their scope is the job desk only.
        Route::get('/contractor/maintenance', [ContractorController::class, 'contractorIndex'])
            ->name('contractor.maintenance.index')
            ->defaults('description', 'View my assigned maintenance jobs');
        // Maintenance track & close (Wave 5 slice 3)
        Route::post('/contractor/maintenance/{maintenanceRequest}/start', [ContractorController::class, 'startJob'])
            ->name('contractor.maintenance.start')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Start work on an assigned maintenance job');
        Route::post('/contractor/maintenance/{maintenanceRequest}/complete', [ContractorController::class, 'completeJob'])
            ->name('contractor.maintenance.complete')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Mark an in-progress maintenance job complete');
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

        // Subscription Plans CRUD (M12)
        Route::get('subscriptions/plans', [AdminPlansController::class, 'index'])
            ->name('subscriptions.plans.index')
            ->defaults('description', 'Manage subscription plans');
        Route::post('subscriptions/plans', [AdminPlansController::class, 'store'])
            ->name('subscriptions.plans.store')
            ->defaults('description', 'Create a subscription plan');
        Route::patch('subscriptions/plans/{plan}', [AdminPlansController::class, 'update'])
            ->name('subscriptions.plans.update')
            ->defaults('description', 'Update a subscription plan');
        Route::delete('subscriptions/plans/{plan}', [AdminPlansController::class, 'destroy'])
            ->name('subscriptions.plans.destroy')
            ->defaults('description', 'Archive or delete a subscription plan');
        Route::put('subscriptions/plans/{plan}/features', [AdminPlansController::class, 'features'])
            ->name('subscriptions.plans.features')
            ->defaults('description', 'Toggle plan feature grants');

        // Configuration Centre (M24)
        Route::get('configuration', [AdminConfigController::class, 'index'])
            ->name('configuration.index')
            ->defaults('description', 'Configuration Centre — commercial rules as data');
        Route::patch('configuration', [AdminConfigController::class, 'update'])
            ->name('configuration.update')
            ->defaults('description', 'Save configuration changes');

        // Rent payment approvals (M9)
        Route::get('rent/payments', [\App\Http\Controllers\PaymentController::class, 'adminIndex'])
            ->name('rent.payments.index')
            ->defaults('description', 'Approve or reject pending rent payments');
        Route::post('rent/payments/{payment}/approve', [\App\Http\Controllers\PaymentController::class, 'adminApprove'])
            ->name('rent.payments.approve')
            ->defaults('description', 'Settle a pending rent payment');
        Route::post('rent/payments/{payment}/reject', [\App\Http\Controllers\PaymentController::class, 'adminReject'])
            ->name('rent.payments.reject')
            ->defaults('description', 'Reject a pending rent payment');

        // Marketplace analytics + report moderation (Marketplace §35/§43)
        Route::get('marketplace/analytics', [AdminMarketplaceAnalyticsController::class, 'index'])
            ->name('marketplace.analytics')
            ->defaults('description', 'View platform marketplace analytics');
        Route::get('marketplace/reports', [AdminReportController::class, 'index'])
            ->name('marketplace.reports.index')
            ->defaults('description', 'Moderate marketplace reports');
        Route::post('marketplace/reports/{report}/{status}', [AdminReportController::class, 'transition'])
            ->name('marketplace.reports.transition')
            ->whereNumber('report')
            ->defaults('description', 'Update a report status');

        // Featured & advertising approvals (M13, Wave 4 slice 6)
        Route::get('advertising', [AdPlacementController::class, 'adminIndex'])
            ->name('advertising.index')
            ->defaults('description', 'Approve and moderate listing promotions');
        Route::post('advertising/{placement}/approve', [AdPlacementController::class, 'adminApprove'])
            ->name('advertising.approve')
            ->whereNumber('placement')
            ->defaults('description', 'Approve a reserved placement and open its window');
        Route::post('advertising/{placement}/cancel', [AdPlacementController::class, 'adminCancel'])
            ->name('advertising.cancel')
            ->whereNumber('placement')
            ->defaults('description', 'Cancel a placement with a prorated credit');
        Route::post('advertising/{placement}/pause', [AdPlacementController::class, 'adminPause'])
            ->name('advertising.pause')
            ->whereNumber('placement')
            ->defaults('description', 'Pause a live placement');
        Route::post('advertising/{placement}/resume', [AdPlacementController::class, 'adminResume'])
            ->name('advertising.resume')
            ->whereNumber('placement')
            ->defaults('description', 'Resume a paused placement and extend its window');

        // Maintenance escalations (M10, Wave 5 slice 1)
        Route::get('maintenance/escalations', [MaintenanceController::class, 'adminEscalations'])
            ->name('maintenance.escalations.index')
            ->defaults('description', 'Staff queue of maintenance requests past their first-response SLA');
        Route::post('maintenance/escalations/{maintenanceRequest}/ack', [MaintenanceController::class, 'adminAcknowledge'])
            ->name('maintenance.escalations.ack')
            ->whereNumber('maintenanceRequest')
            ->defaults('description', 'Take ownership of an escalated maintenance request');

        // Contractor registry (M11, Wave 5 slice 2)
        Route::get('contractors', [ContractorController::class, 'adminIndex'])
            ->name('contractors.index')
            ->defaults('description', 'Register and verify contractor profiles');
        Route::post('contractors', [ContractorController::class, 'adminStore'])
            ->name('contractors.store')
            ->defaults('description', 'Register a new tradesperson');
        Route::post('contractors/{contractor}/status', [ContractorController::class, 'adminStatus'])
            ->name('contractors.status')
            ->whereNumber('contractor')
            ->defaults('description', 'Update a contractor registry status');
    });
});