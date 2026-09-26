<?php

namespace Tests\Feature\Fixes;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountAdminFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function superuser(): User
    {
        return $this->user('admin@system.local');
    }

    private function admin(): User
    {
        return $this->user('staff@dzimba.local');
    }

    private function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    /** The exact payload the fixed Admin/Users/Edit form submits. */
    private function editPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'status' => $user->status,
            'password' => '',
            'password_confirmation' => '',
            'roles' => $user->roles()->pluck('auth_roles.id')->all(),
        ], $overrides);
    }

    private function makeUser(?string $role = null, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Plain Person',
            'email' => 'plain'.uniqid().'@example.com',
            'username' => 'plain'.uniqid(),
            'password' => Hash::make('password123'),
            'status' => 'active',
            'password_changed_at' => now(),
        ], $attributes));

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        $company = Company::create(['name' => 'ZimRent HQ']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Harare', 'code' => 'HRE'.uniqid(), 'status' => 'active']);
        $department = Department::create(['branch_id' => $branch->id, 'name' => 'Support', 'code' => 'SUP'.uniqid(), 'status' => 'active']);

        return Employee::create(array_merge([
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employee_number' => 'EMP'.random_int(1000, 9999),
            'first_name' => 'Rudo',
            'last_name' => 'Moyo',
            'employment_type' => 'full_time',
            'status' => 'active',
        ], $attributes));
    }

    // Bug 1: System Users edit

    public function test_edit_form_payload_updates_profile_status_and_roles(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $tenant), $this->editPayload($tenant, [
                'name' => 'Renamed Tenant',
                'username' => 'renamed-tenant',
                'roles' => [$this->roleId('Tenant'), $this->roleId('Owner')],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('auth.users.index'));

        $tenant->refresh();
        $this->assertSame('Renamed Tenant', $tenant->name);
        $this->assertSame('renamed-tenant', $tenant->username);
        $this->assertTrue($tenant->hasRole('Owner'));
        $this->assertSame($this->admin()->id, (int) $tenant->roles()->where('name', 'Owner')->first()->pivot->assigned_by);
    }

    public function test_admin_grants_owner_role_and_user_can_open_owner_dashboard(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $tenant), $this->editPayload($tenant, [
                'roles' => [$this->roleId('Tenant'), $this->roleId('Owner')],
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($tenant->fresh())->get('/owner')->assertOk();
    }

    public function test_edit_password_requires_confirmation_and_forces_change(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $tenant), $this->editPayload($tenant, [
                'password' => 'NewSecret123',
                'password_confirmation' => 'Mismatch123',
            ]))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $tenant), $this->editPayload($tenant, [
                'password' => 'NewSecret123',
                'password_confirmation' => 'NewSecret123',
            ]))
            ->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertTrue(Hash::check('NewSecret123', $tenant->password));
        $this->assertNull($tenant->password_changed_at);
    }

    public function test_only_superuser_can_grant_superuser_role(): void
    {
        $tenant = $this->user('tenant@dzimba.local');
        $payload = $this->editPayload($tenant, ['roles' => [$this->roleId('Tenant'), $this->roleId('Superuser')]]);

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $tenant), $payload)
            ->assertSessionHasErrors('roles');
        $this->assertFalse($tenant->fresh()->hasRole('Superuser'));

        $this->actingAs($this->superuser())
            ->patch(route('auth.users.update', $tenant), $payload)
            ->assertSessionHasNoErrors();
        $this->assertTrue($tenant->fresh()->hasRole('Superuser'));
    }

    public function test_admin_cannot_edit_a_superuser_account(): void
    {
        $super = $this->superuser();

        $this->actingAs($this->admin())
            ->patch(route('auth.users.update', $super), $this->editPayload($super, ['email' => 'hijack@example.com']))
            ->assertSessionHasErrors('roles');

        $this->assertSame('admin@system.local', $super->fresh()->email);
    }

    public function test_admin_cannot_remove_own_admin_role_or_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('auth.users.update', $admin), $this->editPayload($admin, ['roles' => [$this->roleId('Staff')]]))
            ->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole('Admin'));

        $this->actingAs($admin)
            ->patch(route('auth.users.update', $admin), $this->editPayload($admin, ['status' => 'inactive']))
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $admin->fresh()->status);
    }

    // Bug 2: bulk assign / remove

    public function test_bulk_assign_and_remove_use_real_routes_and_record_assigner(): void
    {
        $this->assertTrue(Route::has('auth.roles.bulk-assign.store'));
        $this->assertTrue(Route::has('auth.roles.bulk-remove.store'));
        $this->assertFalse(Route::has('roles.bulk-assign.store'));

        $tenant = $this->user('tenant@dzimba.local');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('auth.roles.bulk-assign.store'), ['role_ids' => [$this->roleId('Owner')], 'user_ids' => [$tenant->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('auth.roles.index'));

        $owner = $tenant->roles()->where('name', 'Owner')->first();
        $this->assertNotNull($owner);
        $this->assertSame($admin->id, (int) $owner->pivot->assigned_by);
        $this->assertTrue($tenant->hasRole('Tenant'));

        $this->actingAs($admin)
            ->post(route('auth.roles.bulk-remove.store'), ['role_ids' => [$this->roleId('Owner')], 'user_ids' => [$tenant->id]])
            ->assertSessionHasNoErrors();

        $this->assertFalse($tenant->hasRole('Owner'));
        $this->assertTrue($tenant->hasRole('Tenant'));
    }

    public function test_bulk_operations_apply_superuser_and_self_guardrails(): void
    {
        $tenant = $this->user('tenant@dzimba.local');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('auth.roles.bulk-assign.store'), ['role_ids' => [$this->roleId('Superuser')], 'user_ids' => [$tenant->id]])
            ->assertSessionHasErrors('role_ids');
        $this->assertFalse($tenant->hasRole('Superuser'));

        $this->actingAs($admin)
            ->post(route('auth.roles.bulk-remove.store'), ['role_ids' => [$this->roleId('Admin')], 'user_ids' => [$admin->id]])
            ->assertSessionHasErrors('role_ids');
        $this->assertTrue($admin->hasRole('Admin'));
    }

    // Bug 3: toggle status uses PATCH

    public function test_users_list_toggle_status_via_patch(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($this->admin())
            ->patch(route('auth.users.toggle-status', $tenant))
            ->assertSessionHas('success');
        $this->assertSame('inactive', $tenant->fresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('auth.users.toggle-status', $this->admin()))
            ->assertSessionHas('error', 'You cannot deactivate your own account.');
        $this->assertSame('active', $this->admin()->status);
    }

    // Bug 4: role filter

    public function test_role_filter_uses_role_name_sent_by_ui(): void
    {
        $this->actingAs($this->admin())
            ->get(route('auth.users.index', ['role' => 'Owner']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'owner@dzimba.local'));
    }

    // Bug 5: inactive users

    public function test_inactive_user_cannot_sign_in(): void
    {
        $this->user('tenant@dzimba.local')->update(['status' => 'inactive']);

        $this->from('/login')
            ->post(route('login.submit'), ['login' => 'tenant@dzimba.local', 'password' => 'password123'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', AuthService::DEACTIVATED_MESSAGE);

        $this->assertGuest();
    }

    public function test_inactive_user_with_wrong_password_gets_generic_error(): void
    {
        $this->user('tenant@dzimba.local')->update(['status' => 'inactive']);

        $this->from('/login')
            ->post(route('login.submit'), ['login' => 'tenant@dzimba.local', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('error');
    }

    public function test_signed_in_user_is_logged_out_after_deactivation(): void
    {
        $tenant = $this->user('tenant@dzimba.local');
        $this->actingAs($tenant)->get('/tenant')->assertOk();

        $tenant->update(['status' => 'inactive']);

        $this->get('/tenant')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', AuthService::DEACTIVATED_MESSAGE);
        $this->assertGuest();
    }

    public function test_auth_management_deactivate_blocks_sign_in(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($this->admin())
            ->patch(route('auth.management.toggle-status', $tenant))
            ->assertSessionHas('success');
        Auth::logout();

        $this->post(route('login.submit'), ['login' => 'tenant', 'password' => 'password123'])
            ->assertSessionHas('error', AuthService::DEACTIVATED_MESSAGE);
        $this->assertGuest();
    }

    // Bug 6: lockout message + unlock

    public function test_lockout_message_is_shown_on_login_page_and_admin_can_unlock(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->from('/login')->post(route('login.submit'), ['login' => 'tenant', 'password' => 'wrong']);
        }

        $this->from('/login')
            ->followingRedirects()
            ->post(route('login.submit'), ['login' => 'tenant', 'password' => 'wrong'])
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('flash.error', AuthService::LOCKED_MESSAGE));

        $tenant = $this->user('tenant@dzimba.local');
        $this->assertNotNull($tenant->locked_at);

        // Correct password still blocked while locked, even if status flips.
        $tenant->update(['status' => 'active']);
        $this->post(route('login.submit'), ['login' => 'tenant', 'password' => 'password123'])
            ->assertSessionHas('error', AuthService::LOCKED_MESSAGE);
        $this->assertGuest();

        $this->actingAs($this->admin())
            ->get(route('auth.management', ['search' => 'tenant@dzimba.local']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Management')
                ->where('users.data.0.email', 'tenant@dzimba.local')
                ->where('users.data.0.locked_at', fn ($value) => $value !== null));

        $this->actingAs($this->admin())
            ->post(route('auth.management.unlock', $tenant))
            ->assertSessionHas('success');
        Auth::logout();

        $this->assertNull($tenant->fresh()->locked_at);
        $this->post(route('login.submit'), ['login' => 'tenant', 'password' => 'password123'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertAuthenticatedAs($tenant);
    }

    // Bug 7: reset password

    public function test_reset_for_user_without_employee_generates_one_time_password(): void
    {
        $tenant = $this->user('tenant@dzimba.local');

        $response = $this->actingAs($this->admin())
            ->post(route('auth.management.reset', $tenant))
            ->assertSessionHas('success');

        preg_match('/Temporary password: (\S+)/', session('success'), $matches);
        $temporary = $matches[1] ?? '';
        $this->assertGreaterThanOrEqual(14, strlen($temporary));

        $tenant->refresh();
        $this->assertTrue(Hash::check($temporary, $tenant->password));
        $this->assertNull($tenant->password_changed_at);

        Auth::logout();
        $this->post(route('login.submit'), ['login' => 'tenant', 'password' => $temporary]);
        $this->assertAuthenticatedAs($tenant);
        $this->get('/tenant')->assertRedirect(route('password.change'));
    }

    public function test_reset_for_employee_uses_lowercase_surname(): void
    {
        $user = $this->makeUser('Staff');
        $this->makeEmployee(['user_id' => $user->id, 'last_name' => 'Chikwanha']);

        $this->actingAs($this->admin())
            ->post(route('auth.management.reset', $user))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('chikwanha', $user->fresh()->password));
    }

    public function test_admin_cannot_reset_superuser_password(): void
    {
        $super = $this->superuser();
        $hash = $super->password;

        $this->actingAs($this->admin())
            ->post(route('auth.management.reset', $super))
            ->assertSessionHas('error', 'Only a Superuser can change a Superuser account.');

        $this->assertSame($hash, $super->fresh()->password);
    }

    // Bug 8: auth management pagination + search

    public function test_auth_management_is_searched_and_paginated_server_side(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeUser('Tenant');
        }

        $this->actingAs($this->admin())
            ->get(route('auth.management', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Management')
                ->where('users.current_page', 2)
                ->where('users.total', User::count())
                ->has('users.data'));

        $this->actingAs($this->admin())
            ->get(route('auth.management', ['search' => 'owner@dzimba']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.email', 'owner@dzimba.local')
                ->where('filters.search', 'owner@dzimba'));
    }

    // Bug 9: remember me

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $response = $this->post(route('login.submit'), ['login' => 'tenant', 'password' => 'password123', 'remember' => true]);
        $response->assertCookie(Auth::guard()->getRecallerName());

        Auth::logout();

        $response = $this->post(route('login.submit'), ['login' => 'owner', 'password' => 'password123', 'remember' => false]);
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    // Bug 10: /dashboard

    public function test_dashboard_redirects_each_role_to_its_own_dashboard(): void
    {
        $cases = [
            'admin@system.local' => 'admin.dashboard',
            'staff@dzimba.local' => 'admin.dashboard',
            'owner@dzimba.local' => 'owner.dashboard',
            'tenant@dzimba.local' => 'tenant.dashboard',
            'contractor@dzimba.local' => 'contractor.maintenance.index',
        ];

        foreach ($cases as $email => $route) {
            $this->actingAs($this->user($email))->get(route('dashboard'))->assertRedirect(route($route));
        }
    }

    public function test_dashboard_for_role_without_workspace_exposes_no_other_users(): void
    {
        $staff = $this->makeUser('Staff');

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->missing('stats')
                ->missing('recent_users'))
            ->assertDontSee('owner@dzimba.local')
            ->assertDontSee('tenant@dzimba.local');
    }

    public function test_change_password_lands_on_role_dashboard(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $tenant = $this->user('tenant@dzimba.local');

        $this->actingAs($tenant)
            ->get(route('password.change'))
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ChangePassword')->where('forced', false));

        $this->actingAs($tenant)
            ->put(route('password.update'), [
                'current_password' => 'password123',
                'password' => 'Brand-New!Pass9',
                'password_confirmation' => 'Brand-New!Pass9',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tenant.dashboard'))
            ->assertSessionHas('success');
    }

    // Bug 12: missing Inertia pages

    public function test_employee_branch_and_department_pages_render(): void
    {
        $employee = $this->makeEmployee();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.employees.show', $employee))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Employees/Show'));
        $this->actingAs($admin)->get(route('admin.employees.edit', $employee))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Employees/Edit'));
        $this->actingAs($admin)->get(route('admin.employees.create-user', $employee))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Employees/CreateUserAccount'));
        $this->actingAs($admin)->get(route('admin.branches.show', $employee->branch_id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Branches/Show'));
        $this->actingAs($admin)->get(route('admin.departments.show', $employee->department_id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Departments/Show'));

        foreach (['Admin/Employees/Show', 'Admin/Employees/Edit', 'Admin/Employees/CreateUserAccount', 'Admin/Branches/Show', 'Admin/Departments/Show'] as $component) {
            $this->assertFileExists(resource_path("js/Pages/{$component}.jsx"));
        }
    }

    // Bug 13: sections resource

    public function test_sections_resource_only_exposes_implemented_actions(): void
    {
        foreach (['create', 'show', 'edit'] as $action) {
            $this->assertFalse(Route::has("admin.sections.{$action}"), "admin.sections.{$action} should not exist");
        }
        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            $this->assertTrue(Route::has("admin.sections.{$action}"));
        }

        $this->actingAs($this->admin())->get('/admin/sections/create')->assertMethodNotAllowed();
    }

    // Bug 14: employee email handling

    public function test_create_user_account_requires_an_email(): void
    {
        $employee = $this->makeEmployee(['email' => null]);
        $before = User::count();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store-user', $employee), [
                'email' => '',
                'username' => 'rmoyo',
                'password' => 'Temp-pass-123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($before, User::count());
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_create_user_account_saves_email_and_forces_password_change(): void
    {
        $employee = $this->makeEmployee(['email' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store-user', $employee), [
                'email' => 'rudo@zimrent.test',
                'username' => 'rmoyo',
                'password' => 'Temp-pass-123',
                'role_ids' => [$this->roleId('Staff')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.employees.show', $employee));

        $employee->refresh();
        $this->assertSame('rudo@zimrent.test', $employee->email);
        $this->assertSame('rudo@zimrent.test', $employee->user->email);
        $this->assertNull($employee->user->password_changed_at);
        $this->assertTrue($employee->user->hasRole('Staff'));
    }

    public function test_create_user_account_cannot_grant_superuser_as_admin(): void
    {
        $employee = $this->makeEmployee(['email' => 'rudo@zimrent.test']);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store-user', $employee), [
                'email' => 'rudo@zimrent.test',
                'username' => 'rmoyo',
                'password' => 'Temp-pass-123',
                'role_ids' => [$this->roleId('Superuser')],
            ])
            ->assertSessionHasErrors('role_ids');

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_employee_store_with_account_requires_email(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), [
                'employee_number' => 'EMP9001',
                'first_name' => 'Tariro',
                'last_name' => 'Ncube',
                'employment_type' => 'full_time',
                'status' => 'active',
                'create_user_account' => true,
                'email' => '',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('employees', ['employee_number' => 'EMP9001']);
    }

    public function test_employee_update_cannot_blank_linked_user_email(): void
    {
        $user = $this->makeUser('Staff', ['email' => 'linked@zimrent.test']);
        $employee = $this->makeEmployee(['user_id' => $user->id, 'email' => 'linked@zimrent.test']);

        $payload = [
            'employee_number' => $employee->employee_number,
            'first_name' => 'Rudo',
            'last_name' => 'Moyo',
            'employment_type' => 'full_time',
            'status' => 'active',
            'email' => '',
        ];

        $this->actingAs($this->admin())
            ->patch(route('admin.employees.update', $employee), $payload)
            ->assertSessionHasErrors('email');
        $this->assertSame('linked@zimrent.test', $user->fresh()->email);

        $this->actingAs($this->admin())
            ->patch(route('admin.employees.update', $employee), array_merge($payload, ['email' => 'owner@dzimba.local']))
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin())
            ->patch(route('admin.employees.update', $employee), array_merge($payload, ['email' => 'new@zimrent.test']))
            ->assertSessionHasNoErrors();
        $this->assertSame('new@zimrent.test', $user->fresh()->email);
    }

    // Bug 15: system roles cannot be renamed

    public function test_system_roles_cannot_be_renamed_but_custom_roles_can(): void
    {
        foreach (AuthService::SYSTEM_ROLES as $name) {
            $role = Role::where('name', $name)->first();
            $this->actingAs($this->superuser())
                ->patch(route('auth.roles.update', $role), ['name' => $name.' Renamed', 'description' => 'x'])
                ->assertSessionHasErrors('name');
            $this->assertSame($name, $role->fresh()->name);
        }

        $superuser = Role::where('name', 'Superuser')->first();
        $this->actingAs($this->superuser())
            ->patch(route('auth.roles.update', $superuser), ['name' => 'Superuser', 'description' => 'Updated description'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Updated description', $superuser->fresh()->description);

        $staff = Role::where('name', 'Staff')->first();
        $this->actingAs($this->superuser())
            ->patch(route('auth.roles.update', $staff), ['name' => 'Support Staff'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Support Staff', $staff->fresh()->name);
    }

    // Bug 16: safe deletion

    public function test_user_with_history_or_self_cannot_be_deleted(): void
    {
        $owner = $this->user('owner@dzimba.local');

        $this->actingAs($this->admin())
            ->delete(route('auth.users.destroy', $owner))
            ->assertSessionHas('error');
        $this->assertNotNull($owner->fresh());

        $this->actingAs($this->admin())
            ->delete(route('auth.users.destroy', $this->admin()))
            ->assertSessionHas('error', 'You cannot delete your own account.');

        $this->actingAs($this->admin())
            ->delete(route('auth.users.destroy', $this->superuser()))
            ->assertSessionHas('error', 'Only a Superuser can change a Superuser account.');
        $this->assertNotNull($this->superuser());
    }

    public function test_user_without_history_can_be_deleted(): void
    {
        $plain = $this->makeUser('Staff');

        $this->actingAs($this->admin())
            ->delete(route('auth.users.destroy', $plain))
            ->assertRedirect(route('auth.users.index'))
            ->assertSessionHas('success');

        $this->assertNull(User::find($plain->id));
    }
}
