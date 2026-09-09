<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingAndDiscoverTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '3 Bedroom House in Marlborough',
            'description' => 'Spacious and secure.',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'building_size' => 200,
            'land_size' => 900,
            'price' => 750,
            'deposit' => 750,
            'furnished' => false,
            'status' => 'available',
            'suburb' => 'Marlborough',
            'zone' => 'Harare West',
            'city' => 'Harare',
            'address' => '12 Marlborough Drive',
            'amenities' => ['borehole', 'garden'],
            'available_from' => now()->addDays(14)->toDateString(),
        ], $overrides);
    }

    public function test_owner_can_create_a_property(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('properties', [
            'title' => '3 Bedroom House in Marlborough',
            'owner_id' => $this->owner()->id,
            'zone' => 'Harare West',
            'building_size' => 200.00,
            'land_size' => 900.00,
            'status' => 'available',
        ]);
    }

    public function test_created_property_belongs_to_the_signed_in_owner(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->validPayload())
            ->assertRedirect();

        $property = Property::where('title', '3 Bedroom House in Marlborough')->first();

        $this->assertSame($this->owner()->id, $property->owner_id);
    }

    public function test_owner_can_view_their_own_properties_page(): void
    {
        $this->actingAs($this->owner())
            ->get('/owner/properties')
            ->assertOk();
    }

    public function test_owner_can_view_own_property_show_page(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->owner())
            ->get('/owner/properties/'.$property->id)
            ->assertOk();
    }

    public function test_owner_cannot_view_another_owners_property(): void
    {
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other-owner@dzimba.local',
            'username' => 'other-owner',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $ownerRole = \App\Models\Role::where('name', 'Owner')->first();
        $otherOwner->roles()->attach($ownerRole);

        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($otherOwner)
            ->get('/owner/properties/'.$property->id)
            ->assertNotFound();

        $this->actingAs($otherOwner)
            ->get('/owner/properties/'.$property->id.'/edit')
            ->assertNotFound();
    }

    public function test_tenant_cannot_access_owner_property_routes(): void
    {
        $this->actingAs($this->tenant())
            ->get('/owner/properties')
            ->assertForbidden();

        $this->actingAs($this->tenant())
            ->get('/owner/properties/create')
            ->assertForbidden();

        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->tenant())
            ->post('/owner/properties', $this->validPayload())
            ->assertForbidden();

        $this->actingAs($this->tenant())
            ->put('/owner/properties/'.$property->id, $this->validPayload())
            ->assertForbidden();

        $this->actingAs($this->tenant())
            ->delete('/owner/properties/'.$property->id)
            ->assertForbidden();
    }

    public function test_validation_errors_block_property_creation(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->validPayload([
                'property_type' => 'castle',
                'bedrooms' => -1,
                'price' => 'not-a-number',
            ]))
            ->assertSessionHasErrors(['property_type', 'bedrooms', 'price']);
    }

    public function test_owner_can_update_own_property(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();
        $originalStatus = $property->status;

        $this->actingAs($this->owner())
            ->put('/owner/properties/'.$property->id, $this->validPayload([
                'title' => 'Updated 4 Bedroom House',
                'bedrooms' => 4,
                'price' => 950,
            ]))
            ->assertRedirect();

        $property->refresh();

        $this->assertSame('Updated 4 Bedroom House', $property->title);
        $this->assertSame(4, $property->bedrooms);
        $this->assertSame(950.00, (float) $property->price);
        $this->assertSame($originalStatus, $property->status);
    }

    public function test_owner_can_delete_own_property(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->owner())
            ->delete('/owner/properties/'.$property->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    public function test_only_available_properties_appear_on_the_marketplace(): void
    {
        Property::where('owner_id', $this->owner()->id)->update(['status' => 'occupied']);

        $available = Property::where('status', 'available')->count();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', $available));
    }

    public function test_allowed_status_transitions_change_status_and_write_history(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();
        $this->assertSame('available', $property->status);

        $allowed = [
            ['available', 'reserved'],
            ['reserved', 'occupied'],
            ['occupied', 'available'],
            ['available', 'unavailable'],
            ['unavailable', 'available'],
        ];

        foreach ($allowed as [$from, $to]) {
            $this->assertSame($from, $property->status);
            $this->actingAs($this->owner())
                ->put('/owner/properties/'.$property->id.'/status', ['status' => $to])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertSame($to, $property->refresh()->status);
        }

        $this->assertDatabaseCount('property_history', count($allowed));
        $this->assertDatabaseHas('property_history', [
            'property_id' => $property->id,
            'from_status' => 'available',
            'to_status' => 'reserved',
            'changed_by' => $this->owner()->id,
        ]);
    }

    public function test_blocked_status_transitions_are_rejected_without_history(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();
        $this->assertSame('available', $property->status);

        $blocked = ['occupied', 'available'];

        foreach ($blocked as $to) {
            $this->actingAs($this->owner())
                ->put('/owner/properties/'.$property->id.'/status', ['status' => $to])
                ->assertSessionHasErrors('status');

            $this->assertSame('available', $property->refresh()->status);
        }

        $this->assertDatabaseCount('property_history', 0);
    }

    public function test_same_status_transition_is_rejected(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->owner())
            ->put('/owner/properties/'.$property->id.'/status', ['status' => 'available'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('property_history', 0);
    }

    public function test_status_cannot_be_changed_by_tenant_or_admin(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->tenant())
            ->put('/owner/properties/'.$property->id.'/status', ['status' => 'reserved'])
            ->assertForbidden();

        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)
            ->put('/owner/properties/'.$property->id.'/status', ['status' => 'reserved'])
            ->assertForbidden();
    }

    public function test_owner_cannot_change_another_owners_property_status(): void
    {
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other-owner@dzimba.local',
            'username' => 'other-owner',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $ownerRole = \App\Models\Role::where('name', 'Owner')->first();
        $otherOwner->roles()->attach($ownerRole);

        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($otherOwner)
            ->put('/owner/properties/'.$property->id.'/status', ['status' => 'unavailable'])
            ->assertNotFound();

        $this->assertDatabaseCount('property_history', 0);
    }

    public function test_show_page_contains_status_history(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->first();

        $this->actingAs($this->owner())
            ->put('/owner/properties/'.$property->id.'/status', ['status' => 'reserved'])
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->get('/owner/properties/'.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Properties/Show')
                ->has('property.history', 1));
    }

    public function test_marketplace_filters_by_property_type(): void
    {
        $this->get('/?property_type=flat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 1)
                ->where('properties.0.title', '2 Bedroom Flat with Generator Backup'));
    }

    public function test_marketplace_filters_by_zone(): void
    {
        $this->get('/?zone=Harare North')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 1)
                ->where('properties.0.title', 'Modern 3 Bedroom House in Borrowdale'));
    }

    public function test_marketplace_price_applies_as_true_min_max_range(): void
    {
        $this->get('/?min_price=500&max_price=1000')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 2));
    }

    public function test_marketplace_filter_combination_returns_exact_set(): void
    {
        $this->get('/?city=Harare&min_price=400&max_price=900&bedrooms=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 2));
    }

    public function test_marketplace_filters_by_furnished(): void
    {
        $this->get('/?furnished=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 3));

        $this->get('/?furnished=0')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 2));
    }

    public function test_marketplace_bedrooms_and_bathrooms_use_at_least_semantics(): void
    {
        $this->get('/?bedrooms=3')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 2));

        $this->get('/?bathrooms=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 3));
    }

    public function test_marketplace_sorts_featured_first_then_by_price(): void
    {
        $this->get('/?sort=price_asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->where('properties.0.title', 'Townhouse near Bulawayo CBD')
                ->where('properties.1.title', 'Modern 3 Bedroom House in Borrowdale')
                ->where('properties.2.title', 'Bachelor Room in Msasa'));
    }

    public function test_marketplace_ignores_invalid_filter_values(): void
    {
        $this->get('/?price=not-a-number&property_type=castle&bedrooms=abc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 5));
    }

    public function test_marketplace_paginates_results(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            Property::create([
                'owner_id' => $this->owner()->id,
                'title' => "Extra House $i",
                'property_type' => 'house',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'price' => 300 + $i,
                'status' => 'available',
                'city' => 'Harare',
                'suburb' => 'Borrowdale',
            ]);
        }

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 12)
                ->where('total', 18)
                ->where('pagination.last_page', 2)
                ->where('pagination.current_page', 1));

        $this->get('/?page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 6)
                ->where('pagination.current_page', 2));
    }

    public function test_public_property_detail_renders_for_available_listing(): void
    {
        $property = Property::where('status', 'available')->first();

        $this->get('/properties/'.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Show')
                ->where('property.title', $property->title)
                ->where('property.owner.name', $property->owner->name)
                ->where('property.owner.email', $property->owner->email)
                ->has('property.images'));
    }

    public function test_public_property_detail_404_for_hidden_listings(): void
    {
        $property = Property::where('status', '!=', 'available')->first();
        $this->assertNotNull($property);

        foreach (['reserved', 'occupied', 'unavailable'] as $status) {
            $property->update(['status' => $status]);
            $this->get('/properties/'.$property->id)->assertNotFound();
        }
    }

    public function test_public_property_detail_404_for_missing_property(): void
    {
        $this->get('/properties/99999')->assertNotFound();
    }

    public function test_tenant_can_toggle_a_favourite_on_and_off(): void
    {
        $tenant = $this->tenant();
        $property = Property::where('status', 'available')->first();
        $tenant->favouritedProperties()->detach();

        $this->actingAs($tenant)->post('/tenant/favourites/'.$property->id)
            ->assertRedirect();

        $this->assertDatabaseHas('property_favourites', [
            'user_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($tenant)->post('/tenant/favourites/'.$property->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('property_favourites', [
            'user_id' => $tenant->id,
            'property_id' => $property->id,
        ]);
    }

    public function test_favourite_toggle_never_duplicates_rows(): void
    {
        $tenant = $this->tenant();
        $property = Property::where('status', 'available')->first();
        $tenant->favouritedProperties()->detach();

        $tenant->favouritedProperties()->attach($property->id);
        $this->actingAs($tenant)->post('/tenant/favourites/'.$property->id)->assertRedirect();

        $this->assertSame(
            0,
            $tenant->favouritedProperties()->where('properties.id', $property->id)->count()
        );
    }

    public function test_marketplace_reports_favourite_state_for_authenticated_tenant(): void
    {
        $tenant = $this->tenant();
        $property = Property::where('status', 'available')->first();
        $tenant->favouritedProperties()->detach();
        $tenant->favouritedProperties()->attach($property->id);

        $this->actingAs($tenant)->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->where('favouriteIds', [$property->id]));
    }

    public function test_guest_sees_empty_favourite_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->where('favouriteIds', []));
    }

    public function test_detail_page_reports_favourite_state_for_authenticated_tenant(): void
    {
        $tenant = $this->tenant();
        $property = Property::where('status', 'available')->first();
        $tenant->favouritedProperties()->detach();
        $tenant->favouritedProperties()->attach($property->id);

        $this->actingAs($tenant)->get('/properties/'.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Show')
                ->where('isFavourited', true));
    }

    public function test_tenant_favourites_page_lists_saved_properties(): void
    {
        $tenant = $this->tenant();
        $property = Property::where('status', 'available')->first();
        $tenant->favouritedProperties()->detach();
        $tenant->favouritedProperties()->attach($property->id);

        $this->actingAs($tenant)->get('/tenant/favourites')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Favourites')
                ->has('favourites', 1)
                ->where('favourites.0.id', $property->id)
                ->where('stats.total', 1)
                ->where('stats.available', 1));
    }

    public function test_guest_is_redirected_to_login_when_opening_favourites(): void
    {
        $this->get('/tenant/favourites')->assertRedirect(route('login'));
    }

    public function test_owner_cannot_access_tenant_favourites(): void
    {
        $this->actingAs($this->owner())->get('/tenant/favourites')->assertForbidden();
    }
}