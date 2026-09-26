<?php

namespace Tests\Feature;

use App\Models\ExpressInterest;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewInterestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function owner(): User
    {
        return User::where('username', 'owner')->first();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->first();
    }

    private function newOwner(): User
    {
        $user = User::factory()->create(['name' => 'Second Owner', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Owner')->first()->id);

        return $user;
    }

    private function newTenant(): User
    {
        $user = User::factory()->create(['name' => 'Second Tenant', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        return $user;
    }

    private function availableProperty(): Property
    {
        return Property::where('status', 'available')->where('owner_id', $this->owner()->id)->first();
    }

    public function test_tenant_can_express_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();

        $this->actingAs($tenant)->post('/tenant/interests/'.$property->id, [
            'note' => 'Please keep me informed.',
        ])->assertRedirect();

        $this->assertDatabaseHas('express_interests', [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'interested',
        ]);

        $this->assertSame(1, $this->owner()->notifications()->where('type', NewInterestNotification::class)->count());
    }

    public function test_interest_is_spam_free_and_idempotent(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();

        $this->actingAs($tenant)->post('/tenant/interests/'.$property->id)->assertRedirect();
        $this->actingAs($tenant)->post('/tenant/interests/'.$property->id)->assertRedirect();

        $this->assertSame(1, ExpressInterest::where('property_id', $property->id)->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, $this->owner()->notifications()->where('type', NewInterestNotification::class)->count());
    }

    public function test_note_is_optional_and_limited_500_chars(): void
    {
        $property = $this->availableProperty();
        $before = ExpressInterest::count();

        $this->actingAs($this->tenant())->post('/tenant/interests/'.$property->id, ['note' => str_repeat('a', 501)])
            ->assertSessionHasErrors('note');

        $this->assertSame($before, ExpressInterest::count());
    }

    public function test_guest_cannot_express_interest(): void
    {
        $property = $this->availableProperty();

        $this->post('/tenant/interests/'.$property->id)->assertRedirect(route('login'));
    }

    public function test_cannot_express_interest_on_unavailable_property(): void
    {
        $property = $this->availableProperty();
        $property->update(['status' => 'reserved']);

        $this->actingAs($this->tenant())->post('/tenant/interests/'.$property->id)->assertNotFound();
    }

    public function test_re_express_reopens_an_archived_row(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'archived',
        ]);

        $this->actingAs($tenant)->post('/tenant/interests/'.$property->id, ['note' => 'Changed my mind — still keen.'])
            ->assertRedirect();

        $this->assertSame('interested', $interest->fresh()->status);
        $this->assertSame('Changed my mind — still keen.', $interest->fresh()->note);
        $this->assertSame(1, ExpressInterest::where('property_id', $property->id)->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, $this->owner()->notifications()->where('type', NewInterestNotification::class)->count());
    }

    public function test_tenant_can_withdraw_own_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'interested',
        ]);

        $this->actingAs($tenant)->post('/tenant/interests/'.$interest->id.'/withdraw')->assertRedirect();

        $this->assertSame('archived', $interest->fresh()->status);
    }

    public function test_tenant_cannot_withdraw_another_tenants_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'interested',
        ]);

        $this->actingAs($this->newTenant())->post('/tenant/interests/'.$interest->id.'/withdraw')->assertNotFound();

        $this->assertSame('interested', $interest->fresh()->status);
    }

    public function test_owner_can_contact_then_reopen_an_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'interested',
        ]);

        $this->actingAs($this->owner())->post('/owner/interests/'.$interest->id.'/contact')->assertRedirect();
        $this->assertSame('contacted', $interest->fresh()->status);

        $this->actingAs($this->owner())->post('/owner/interests/'.$interest->id.'/reopen')->assertRedirect();
        $this->assertSame('interested', $interest->fresh()->status);
    }

    public function test_owner_can_archive_an_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'contacted',
        ]);

        $this->actingAs($this->owner())->post('/owner/interests/'.$interest->id.'/archive')->assertRedirect();

        $this->assertSame('archived', $interest->fresh()->status);
    }

    public function test_archived_interest_cannot_be_contacted(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'archived',
        ]);

        $this->actingAs($this->owner())->post('/owner/interests/'.$interest->id.'/contact')
            ->assertStatus(409);
    }

    public function test_owner_cannot_manage_another_owners_interest(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $interest = ExpressInterest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'interested',
        ]);

        $otherOwner = $this->newOwner();

        $this->actingAs($otherOwner)->post('/owner/interests/'.$interest->id.'/contact')->assertNotFound();
        $this->actingAs($otherOwner)->post('/owner/interests/'.$interest->id.'/archive')->assertNotFound();

        $this->assertSame('interested', $interest->fresh()->status);
    }

    public function test_marketplace_detail_surfaces_interest_state_for_the_tenant(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();

        $this->actingAs($tenant)->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->component('Marketplace/Show')->where('interestState', null));

        $this->actingAs($tenant)->post('/tenant/interests/'.$property->id)->assertRedirect();

        $this->actingAs($tenant)->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->component('Marketplace/Show')
                ->where('interestState.status', 'interested'));
    }

    public function test_guest_detail_page_has_no_interest_state(): void
    {
        $property = $this->availableProperty();

        $this->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->component('Marketplace/Show')->where('interestState', null));
    }
}