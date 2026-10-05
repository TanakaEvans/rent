<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'terms' => true,
        ], $overrides);
    }

    public function test_guest_can_view_the_register_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_public_signup_creates_a_tenant_with_default_role(): void
    {
        // New tenants land on the guided profile builder, not the dashboard.
        $this->post(route('register'), $this->validPayload())
            ->assertRedirect(route('tenant.profile'));

        $user = User::where('email', 'tendai@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Tendai Moyo', $user->name);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->hasRole('Tenant'));
        $this->assertNotNull($user->password_changed_at);
        $this->assertNull($user->locked_at);

        $pivot = $user->roles()->where('name', 'Tenant')->first()?->pivot;
        $this->assertNull($pivot?->assigned_by);
    }

    public function test_signup_auto_logs_in_the_new_tenant(): void
    {
        $this->post(route('register'), $this->validPayload());

        $user = User::where('email', 'tendai@example.com')->first();
        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $tenant = User::where('username', 'tenant')->first();

        $this->post(route('register'), $this->validPayload(['email' => $tenant->email]))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', $tenant->email)->count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post(route('register'), $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'tendai@example.com')->first());
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $this->post(route('register'), $this->validPayload(['password_confirmation' => 'Different123']))
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'tendai@example.com')->first());
    }

    public function test_missing_terms_are_rejected(): void
    {
        $this->post(route('register'), $this->validPayload(['terms' => false]))
            ->assertSessionHasErrors('terms');

        $this->assertNull(User::where('email', 'tendai@example.com')->first());
    }

    public function test_username_is_derived_from_the_email(): void
    {
        $this->post(route('register'), $this->validPayload(['email' => 'tendai.moyo@example.com']));

        $this->assertNotNull(User::where('username', 'tendai.moyo')->first());
    }

    public function test_username_collision_gets_a_numeric_suffix(): void
    {
        $this->post(route('register'), $this->validPayload(['email' => 'jane@first.com']));

        Auth::logout();

        $this->post(route('register'), $this->validPayload([
            'name' => 'Jane Two',
            'email' => 'jane@second.com',
        ]));

        $this->assertNotNull(User::where('username', 'jane')->first());
        $this->assertNotNull(User::where('username', 'jane1')->first());
    }

    public function test_signed_up_tenant_can_log_in_again(): void
    {
        $this->post(route('register'), $this->validPayload());

        Auth::logout();
        $this->post(route('login'), [
            'login' => 'tendai@example.com',
            'password' => 'StrongPass123',
        ])->assertRedirect(route('tenant.dashboard'));
    }

    public function test_authenticated_users_are_redirected_from_register(): void
    {
        $owner = User::where('username', 'owner')->first();

        $this->actingAs($owner)->get(route('register'))->assertRedirect(route('owner.dashboard'));
        $this->actingAs($owner)->post(route('register'), $this->validPayload())
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_owner_signup_creates_an_owner_and_lands_on_the_profile_builder(): void
    {
        // New owners land on account settings (their profile builder).
        $this->post(route('register'), $this->validPayload([
            'email' => 'newlandlord@example.com',
            'role' => 'owner',
        ]))->assertRedirect(route('account.profile'));

        $user = User::where('email', 'newlandlord@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Owner'));
        $this->assertFalse($user->hasRole('Tenant'));
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_signed_up_owner_can_reach_the_owner_dashboard_but_not_admin(): void
    {
        $this->post(route('register'), $this->validPayload([
            'email' => 'landlord2@example.com',
            'role' => 'owner',
        ]));

        $owner = User::where('email', 'landlord2@example.com')->firstOrFail();
        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_missing_role_still_defaults_to_a_tenant(): void
    {
        $this->post(route('register'), $this->validPayload(['email' => 'defaults@example.com']));

        $user = User::where('email', 'defaults@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Tenant'));
        $this->assertFalse($user->hasRole('Owner'));
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $this->post(route('register'), $this->validPayload(['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('auth_users', ['email' => 'tendai@example.com']);
    }

    public function test_register_page_preselects_owner_when_asked(): void
    {
        $this->get(route('register', ['as' => 'owner']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('defaultRole', 'owner'));

        $this->get(route('register'))
            ->assertInertia(fn ($page) => $page->where('defaultRole', 'tenant'));
    }
}