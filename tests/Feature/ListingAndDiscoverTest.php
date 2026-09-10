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

    public function test_marketplace_keyword_search_matches_title_suburb_and_description(): void
    {
        $owner = $this->owner();

        $hitA = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Serene Kamfinsa Cottage',
            'description' => 'Quiet garden cottage near the river.',
            'property_type' => 'cottage',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price' => 350,
            'status' => 'available',
            'suburb' => 'Kamfinsa',
            'city' => 'Harare',
        ]);
        $hitB = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Bright office space',
            'description' => 'Located on the tranquil Avondale river bank',
            'property_type' => 'commercial',
            'bedrooms' => 0,
            'bathrooms' => 1,
            'price' => 1200,
            'status' => 'available',
            'suburb' => 'Avondale',
            'city' => 'Harare',
        ]);
        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Downtown warehouse',
            'description' => 'Industrial unit.',
            'property_type' => 'commercial',
            'bedrooms' => 0,
            'bathrooms' => 0,
            'price' => 2200,
            'status' => 'available',
            'suburb' => 'Willowvale',
            'city' => 'Harare',
        ]);

        $this->get('/?q=tranquil')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketplace/Index')
            ->has('properties', 1)
            ->where('properties.0.id', $hitB->id));

        $this->get('/?q=Kamfinsa')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketplace/Index')
            ->has('properties', 1)
            ->where('properties.0.id', $hitA->id));
    }

    public function test_marketplace_radius_filter_limits_results_around_a_point(): void
    {
        $owner = $this->owner();
        $point = ['lat' => -17.8292, 'lng' => 31.0522];

        $near = Property::create([
            'owner_id' => $owner->id,
            'title' => 'City centre flat',
            'property_type' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 600,
            'status' => 'available',
            'suburb' => 'City Centre',
            'city' => 'Harare',
            'latitude' => -17.8292,
            'longitude' => 31.0522,
        ]);
        $far = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Rural homestead',
            'property_type' => 'house',
            'bedrooms' => 4,
            'bathrooms' => 2,
            'price' => 400,
            'status' => 'available',
            'suburb' => 'Kwe Kwe',
            'city' => 'Gweru',
            'latitude' => -19.45,
            'longitude' => 29.82,
        ]);

        $this->get('/?lat='.$point['lat'].'&lng='.$point['lng'].'&radius_km=5')->assertOk();

        $page = $this->get('/?lat='.$point['lat'].'&lng='.$point['lng'].'&radius_km=5')->viewData('page');
        $ids = array_column($page['props']['properties'], 'id');
        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
        $this->assertSame('available', $far->fresh()->status);
    }

    public function test_marketplace_sorts_by_price_per_square_metre(): void
    {
        $owner = $this->owner();

        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Big but cheap per square',
            'property_type' => 'house',
            'bedrooms' => 4,
            'bathrooms' => 2,
            'price' => 800,
            'building_size' => 200,
            'status' => 'available',
            'suburb' => 'UnitPrice Lane',
            'city' => 'Harare',
        ]);
        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Small but hefty per square',
            'property_type' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 600,
            'building_size' => 100,
            'status' => 'available',
            'suburb' => 'UnitPrice Lane',
            'city' => 'Harare',
        ]);

        $this->get('/?sort=price_per_m2&sort_direction=asc&suburb=UnitPrice+Lane')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketplace/Index')
            ->where('properties.0.title', 'Big but cheap per square'));
    }

    public function test_search_suggestions_return_places_and_property_titles(): void
    {
        $owner = $this->owner();
        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Sunny Hatfield Cottage',
            'property_type' => 'cottage',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 420,
            'status' => 'available',
            'suburb' => 'Hatfield',
            'city' => 'Harare',
        ]);

        $response = $this->get('/search/suggestions?q=Hatfield')->assertOk()->assertJson([]);

        $payload = json_decode($response->getContent(), true);
        $this->assertNotEmpty($payload);
        $this->assertTrue(collect($payload)->contains(fn ($item) => $item['type'] === 'suburb' && $item['label'] === 'Hatfield'));
        $this->assertTrue(collect($payload)->contains(fn ($item) => $item['type'] === 'title' && $item['label'] === 'Sunny Hatfield Cottage'));
    }

    public function test_tenant_can_create_rename_toggle_and_delete_a_saved_search(): void
    {
        $tenant = $this->tenant();

        $this->actingAs($tenant)
            ->post('/tenant/saved-searches', [
                'name' => 'Harare flats',
                'criteria' => ['city' => 'Harare', 'property_type' => 'flat'],
                'notify' => 1,
            ])
            ->assertRedirect(route('tenant.saved-searches.index'));

        $search = $tenant->savedSearches()->firstOrFail();
        $this->assertTrue($search->notify);

        $this->actingAs($tenant)
            ->put('/tenant/saved-searches/'.$search->id, ['name' => 'Cheap Harare flats'])
            ->assertRedirect(route('tenant.saved-searches.index'));

        $this->assertSame('Cheap Harare flats', $search->fresh()->name);

        $this->actingAs($tenant)
            ->put('/tenant/saved-searches/'.$search->id, ['notify' => 0])
            ->assertRedirect(route('tenant.saved-searches.index'));
        $this->assertFalse($search->fresh()->notify);

        $this->actingAs($tenant)
            ->delete('/tenant/saved-searches/'.$search->id)
            ->assertRedirect(route('tenant.saved-searches.index'));

        $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
    }

    public function test_saved_search_index_reports_a_live_match_count(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Property::create([
            'owner_id' => $owner->id,
            'title' => 'The Alley House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850,
            'status' => 'available',
            'suburb' => 'SavedSearch Alley',
            'city' => 'Harare',
        ]);
        Property::create([
            'owner_id' => $owner->id,
            'title' => 'The Alley Flat',
            'property_type' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 650,
            'status' => 'unavailable',
            'suburb' => 'SavedSearch Alley',
            'city' => 'Harare',
        ]);

        $this->actingAs($tenant)
            ->post('/tenant/saved-searches', [
                'name' => 'Alley homes',
                'criteria' => ['suburb' => 'SavedSearch Alley'],
                'notify' => 0,
            ])
            ->assertRedirect(route('tenant.saved-searches.index'));

        $page = $this->actingAs($tenant)
            ->get('/tenant/saved-searches')
            ->assertOk()
            ->viewData('page');

        $mine = collect($page['props']['searches'])->firstWhere('name', 'Alley homes');
        $this->assertNotNull($mine, 'Saved search should be listed on the tenant page.');
        $this->assertSame(1, $mine['match_count'], 'Only the listed property should match.');
    }

    public function test_tenant_cannot_modify_another_tenants_saved_search(): void
    {
        $tenant = $this->tenant();
        $stranger = User::factory()->create(['name' => 'Search Stranger', 'password_changed_at' => now()]);
        $stranger->roles()->attach(\App\Models\Role::where('name', 'Tenant')->first()->id);

        $search = $stranger->savedSearches()->create([
            'name' => 'Private criteria',
            'criteria' => ['city' => 'Mutare'],
            'notify' => false,
        ]);

        $this->actingAs($tenant)
            ->put('/tenant/saved-searches/'.$search->id, ['name' => 'Hi'])
            ->assertNotFound();

        $this->actingAs($tenant)
            ->delete('/tenant/saved-searches/'.$search->id)
            ->assertNotFound();

        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => 'Private criteria']);
    }

    public function test_listing_lifecycle_expires_overdue_listings_with_history(): void
    {
        $owner = User::factory()->create(['name' => 'Lifecycle Owner', 'password_changed_at' => now()]);
        $owner->roles()->attach(\App\Models\Role::where('name', 'Owner')->first()->id);

        $overdue = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Overdue listing',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 700,
            'status' => 'available',
            'suburb' => 'Greendale',
            'city' => 'Harare',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('marketplace:housekeeping')->assertSuccessful();

        $this->assertSame('unavailable', $overdue->fresh()->status);
        $this->assertDatabaseHas('property_history', [
            'property_id' => $overdue->id,
            'from_status' => 'available',
            'to_status' => 'unavailable',
            'note' => 'Listing expired',
        ]);
    }

    public function test_expiry_reminder_notifies_owners_within_the_configured_window(): void
    {
        $owner = User::factory()->create(['name' => 'Reminder Owner', 'password_changed_at' => now()]);
        $owner->roles()->attach(\App\Models\Role::where('name', 'Owner')->first()->id);

        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Due in a fortnight',
            'property_type' => 'flat',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price' => 350,
            'status' => 'available',
            'suburb' => 'Mabelreign',
            'city' => 'Harare',
            'expires_at' => now()->addDays(14),
        ]);
        Property::create([
            'owner_id' => $owner->id,
            'title' => 'Expiring far away',
            'property_type' => 'flat',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price' => 380,
            'status' => 'available',
            'suburb' => 'Mabelreign',
            'city' => 'Harare',
            'expires_at' => now()->addDays(45),
        ]);

        $this->artisan('marketplace:housekeeping')->assertSuccessful();

        $types = $owner->notifications->map(fn ($n) => $n->type)->unique();
        $this->assertTrue($types->contains(\App\Notifications\ListingExpiryReminderNotification::class));
        $this->assertSame(1, $owner->unreadNotifications()->count());
    }

    public function test_owner_can_renew_a_recently_expired_listing(): void
    {
        $owner = User::factory()->create(['name' => 'Renewer', 'password_changed_at' => now()]);
        $owner->roles()->attach(\App\Models\Role::where('name', 'Owner')->first()->id);

        $expired = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Renew me',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'status' => 'unavailable',
            'suburb' => 'Ridgeview',
            'city' => 'Harare',
            'expires_at' => now()->subDays(2),
        ]);

        $this->actingAs($owner)
            ->post('/owner/properties/'.$expired->id.'/renew')
            ->assertRedirect(route('owner.properties.show', $expired->id));

        $fresh = $expired->fresh();
        $this->assertSame('available', $fresh->status);
        $this->assertTrue($fresh->expires_at->isAfter(now()));
        $this->assertDatabaseHas('property_history', [
            'property_id' => $expired->id,
            'from_status' => 'unavailable',
            'to_status' => 'available',
            'note' => 'Listing renewed',
        ]);
    }

    public function test_expired_listing_outside_the_grace_window_cannot_be_renewed(): void
    {
        $owner = User::factory()->create(['name' => 'Too Late', 'password_changed_at' => now()]);
        $owner->roles()->attach(\App\Models\Role::where('name', 'Owner')->first()->id);

        $expired = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Lapsed long ago',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'status' => 'unavailable',
            'suburb' => 'Ridgeview',
            'city' => 'Harare',
            'expires_at' => now()->subDays(10),
        ]);

        $this->actingAs($owner)
            ->post('/owner/properties/'.$expired->id.'/renew')
            ->assertSessionHasErrors('status');

        $this->assertSame('unavailable', $expired->fresh()->status);
    }
}
