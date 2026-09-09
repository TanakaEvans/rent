<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DzimbaAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_owner_can_access_owner_dashboard(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner')->assertOk();
    }

    public function test_tenant_cannot_access_owner_dashboard(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_dashboard(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant')->assertForbidden();
    }

    public function test_tenant_can_access_tenant_dashboard(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/tenant')->assertOk();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_tenant_cannot_access_auth_management(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/auth/management')->assertForbidden();
    }

    public function test_admin_can_access_auth_management(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/auth/management')->assertOk();
    }

    public function test_owner_cannot_access_system_users(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/auth/users')->assertForbidden();
    }

    public function test_marketplace_home_renders(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Marketplace/Index'));
    }

    public function test_guest_can_view_public_property_detail(): void
    {
        $property = \App\Models\Property::where('status', 'available')->first();
        $this->get('/properties/'.$property->id)->assertOk()->assertInertia(fn ($page) => $page->component('Marketplace/Show'));
    }

    public function test_owner_can_access_my_properties(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties')->assertOk();
    }

    public function test_owner_can_access_create_property(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/create')->assertOk();
    }

    public function test_owner_can_access_enquiry_inbox(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/enquiries')->assertOk();
    }

    public function test_tenant_cannot_access_owner_enquiry_inbox(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/enquiries')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_enquiries(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/enquiries')->assertForbidden();
    }

    public function test_tenant_can_access_own_enquiries(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/tenant/enquiries')->assertOk();
    }

    public function test_guest_cannot_access_enquiry_routes(): void
    {
        $this->get('/owner/enquiries')->assertRedirect(route('login'));
        $this->get('/tenant/enquiries')->assertRedirect(route('login'));
    }

    public function test_owner_can_access_viewing_slots_for_their_property(): void
    {
        $property = \App\Models\Property::first();
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/'.$property->id.'/slots')->assertOk();
    }

    public function test_tenant_cannot_access_viewing_slot_routes(): void
    {
        $property = \App\Models\Property::first();
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/properties/'.$property->id.'/slots')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/properties/'.$property->id.'/slots', [])->assertForbidden();
    }

    public function test_guest_cannot_access_viewing_slot_routes(): void
    {
        $property = \App\Models\Property::first();
        $this->get('/owner/properties/'.$property->id.'/slots')->assertRedirect(route('login'));
    }

    public function test_owner_cannot_access_viewing_slots_of_another_owners_property(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner']);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->first()->id);
        $other = \App\Models\Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Someone Else',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 700,
            'status' => 'available',
            'city' => 'Bulawayo',
        ]);
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/'.$other->id.'/slots')->assertNotFound();
    }

    public function test_owner_can_access_viewing_requests(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/viewings')->assertOk();
    }

    public function test_tenant_cannot_access_owner_viewing_routes(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/viewings')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_viewings(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/viewings')->assertForbidden();
    }

    public function test_tenant_can_access_own_viewings(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/viewings')->assertOk();
    }

    public function test_guest_cannot_access_viewing_request_routes(): void
    {
        $this->get('/owner/viewings')->assertRedirect(route('login'));
        $this->get('/tenant/viewings')->assertRedirect(route('login'));
        $this->post('/tenant/viewings', [])->assertRedirect(route('login'));
    }

    public function test_owner_can_access_applications_review(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/applications')->assertOk();
    }

    public function test_tenant_cannot_access_owner_applications_review(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/applications')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/applications/1/approve')->assertForbidden();
    }

    public function test_tenant_can_access_own_applications(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/applications')->assertOk();
    }

    public function test_owner_cannot_access_tenant_applications(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/applications')->assertForbidden();
        $this->actingAs($owner)->post('/tenant/applications/1', [])->assertForbidden();
    }

    public function test_guest_cannot_access_application_routes(): void
    {
        $this->get('/owner/applications')->assertRedirect(route('login'));
        $this->get('/tenant/applications')->assertRedirect(route('login'));
        $this->post('/tenant/applications/1', [])->assertRedirect(route('login'));
    }

    public function test_owner_can_access_own_leases(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/leases')->assertOk();
    }

    public function test_tenant_cannot_access_owner_lease_routes(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/leases')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/applications/1/lease', [])->assertForbidden();
        $this->actingAs($tenant)->post('/owner/leases/1/send', [])->assertForbidden();
        $this->actingAs($tenant)->post('/owner/leases/1/sign', [])->assertForbidden();
    }

    public function test_tenant_can_access_own_leases(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/leases')->assertOk();
    }

    public function test_owner_cannot_access_tenant_leases(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/leases')->assertForbidden();
        $this->actingAs($owner)->post('/tenant/leases/1/sign', [])->assertForbidden();
    }

    public function test_guest_cannot_access_lease_routes(): void
    {
        $this->get('/owner/leases')->assertRedirect(route('login'));
        $this->get('/tenant/leases')->assertRedirect(route('login'));
        $this->post('/owner/applications/1/lease', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/send', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/sign', [])->assertRedirect(route('login'));
        $this->post('/tenant/leases/1/sign', [])->assertRedirect(route('login'));
    }

    public function test_any_authenticated_role_can_access_own_notifications(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/notifications')->assertOk();
        $this->actingAs(User::where('username', 'tenant')->first())->get('/notifications')->assertOk();
        $this->actingAs(User::where('username', 'admin')->first())->get('/notifications')->assertOk();
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
        $this->post('/notifications/read-all')->assertRedirect(route('login'));
    }

    public function test_tenant_cannot_access_my_properties(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/properties')->assertForbidden();
    }

    public function test_tenant_cannot_store_property(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->post('/owner/properties', [])->assertForbidden();
    }

    public function test_admin_cannot_access_owner_properties(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/owner/properties')->assertForbidden();
    }

    public function test_owner_can_change_own_property_status(): void
    {
        $owner = User::where('username', 'owner')->first();
        $property = \App\Models\Property::where('owner_id', $owner->id)->first();
        $this->actingAs($owner)->put('/owner/properties/'.$property->id.'/status', ['status' => 'unavailable'])->assertRedirect();
    }

    public function test_tenant_cannot_change_property_status(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $property = \App\Models\Property::where('owner_id', User::where('username', 'owner')->first()->id)->first();
        $this->actingAs($tenant)->put('/owner/properties/'.$property->id.'/status', ['status' => 'unavailable'])->assertForbidden();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }
}