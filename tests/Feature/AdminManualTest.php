<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_make_an_existing_user_an_owner(): void
    {
        $admin = User::where('email', 'admin@system.local')->firstOrFail();
        $tenant = User::where('email', 'tenant@dzimba.local')->firstOrFail();

        $roleIds = $tenant->roles()->pluck('auth_roles.id')->push(Role::where('name', 'Owner')->value('id'))->all();

        // Exactly what Admin/Users/Edit.jsx submits.
        $this->actingAs($admin)
            ->patch(route('auth.users.update', $tenant), [
                'name' => $tenant->name,
                'email' => $tenant->email,
                'username' => $tenant->username,
                'status' => $tenant->status,
                'password' => '',
                'password_confirmation' => '',
                'roles' => $roleIds,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('auth.users.index'));

        $this->actingAs($tenant->fresh())
            ->get(route('owner.dashboard'))
            ->assertOk();
    }
}
