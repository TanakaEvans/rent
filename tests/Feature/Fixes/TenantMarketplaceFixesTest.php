<?php

namespace Tests\Feature\Fixes;

use App\Models\Enquiry;
use App\Models\ExpressInterest;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\RentalApplication;
use App\Models\Report;
use App\Models\Role;
use App\Models\SavedSearch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Notifications\SavedSearchMatchNotification;
use App\Services\ConfigurationService;
use App\Services\MarketplaceAlertService;
use App\Services\NaturalLanguageSearchService;
use App\Services\SavedSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the tenant marketplace + property detail fixes
 * (B1–B22 of the marketplace bug review).
 */
class TenantMarketplaceFixesTest extends TestCase
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

    private function owner(): User
    {
        return $this->user('owner@dzimba.local');
    }

    private function tenant(): User
    {
        return $this->user('tenant@dzimba.local');
    }

    private function admin(): User
    {
        return $this->user('admin@system.local');
    }

    private function newUser(string $role): User
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        return $user;
    }

    private function listing(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'owner_id' => $this->owner()->id,
            'title' => 'Fixture listing',
            'description' => 'A tidy home.',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'deposit' => 500,
            'currency' => 'USD',
            'payment_terms' => 'monthly',
            'status' => 'available',
            'suburb' => 'Avondale',
            'zone' => 'Harare North',
            'city' => 'Harare',
        ], $overrides));
    }

    private function futureSlot(Property $property): ViewingSlot
    {
        return ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(11, 0),
            'status' => 'available',
        ]);
    }

    private function inertiaPost(User $user, string $uri, array $data = [], string $from = '/')
    {
        // X-Inertia is passed per request so it never leaks into later GETs.
        return $this->actingAs($user)
            ->from($from)
            ->post($uri, $data, ['X-Inertia' => 'true']);
    }

    private function suspendedOwnerListing(array $overrides = []): Property
    {
        app(ConfigurationService::class)->set('subscriptions.suspension.behaviour', 'hide_listings', $this->admin()->id, 'Hide suspended owners.', $this->admin()->id);

        $owner = $this->newUser('Owner');
        $owner->subscriptions()->create([
            'plan_id' => SubscriptionPlan::where('name', 'Free')->firstOrFail()->id,
            'status' => 'suspended',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'cycle' => 'monthly',
            'details' => null,
        ]);

        return $this->listing(array_merge([
            'owner_id' => $owner->id,
            'title' => 'Zyxwv Suspended Villa',
            'suburb' => 'Qwertyville',
            'zone' => 'Qwerty Zone',
            'city' => 'Qwertytown',
            'featured' => true,
        ], $overrides));
    }

    // B1 — viewing booking

    public function test_b1_detail_page_gives_tenant_the_slots_for_the_viewing_form(): void
    {
        $property = $this->listing();
        $slot = $this->futureSlot($property);

        $this->actingAs($this->tenant())->get('/properties/'.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Show')
                ->where('property.id', $property->id)
                ->where('viewingSlots.0.id', $slot->id));
    }

    public function test_b1_the_fixed_viewing_form_payload_books_a_viewing(): void
    {
        $property = $this->listing();
        $slot = $this->futureSlot($property);

        // Exactly what Show.jsx now sends: property_id + slot_id + request_message.
        $this->inertiaPost($this->tenant(), '/tenant/viewings', [
            'property_id' => $property->id,
            'slot_id' => (string) $slot->id,
            'request_message' => 'Saturday morning works.',
        ], '/properties/'.$property->id)
            ->assertRedirect('/properties/'.$property->id)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('viewing_requests', [
            'property_id' => $property->id,
            'slot_id' => $slot->id,
            'tenant_id' => $this->tenant()->id,
            'status' => 'requested',
        ]);
    }

    public function test_b1_the_old_payload_without_property_id_is_rejected(): void
    {
        $property = $this->listing();
        $slot = $this->futureSlot($property);

        $this->inertiaPost($this->tenant(), '/tenant/viewings', [
            'slot_id' => $slot->id,
            'request_message' => '',
        ], '/properties/'.$property->id)->assertSessionHasErrors('property_id');
    }

    // B2 — Save search chip

    public function test_b2_shared_roles_are_objects_with_a_name(): void
    {
        $this->actingAs($this->tenant())->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->where('auth.user.roles', fn ($roles) => collect($roles)->pluck('name')->contains('Tenant')));
    }

    public function test_b2_tenant_can_save_the_current_marketplace_search(): void
    {
        $this->inertiaPost($this->tenant(), '/tenant/saved-searches', [
            'name' => 'Flats in Harare',
            'criteria' => ['property_type' => 'flat', 'city' => 'Harare', 'sort' => 'newest'],
            'notify' => true,
        ])->assertRedirect(route('tenant.saved-searches.index'));

        $this->assertDatabaseHas('saved_searches', ['user_id' => $this->tenant()->id, 'name' => 'Flats in Harare']);
    }

    // B3 — favourites are tenant-only

    public function test_b3_non_tenants_cannot_favourite_and_are_not_tenants_on_the_page(): void
    {
        $property = $this->listing();

        foreach ([$this->owner(), $this->admin()] as $user) {
            $this->actingAs($user)->get('/')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('auth.user.roles', fn ($roles) => ! collect($roles)->pluck('name')->contains('Tenant')));

            $this->inertiaPost($user, '/tenant/favourites/'.$property->id);
            $this->assertDatabaseMissing('property_favourites', ['property_id' => $property->id, 'user_id' => $user->id]);
        }
    }

    // B6 — errors reach the detail page

    public function test_b6_interest_on_an_off_market_home_flashes_an_error_back(): void
    {
        $property = $this->listing(['status' => 'reserved']);

        $this->inertiaPost($this->tenant(), '/tenant/interests/'.$property->id, [], '/properties/'.$property->id)
            ->assertRedirect('/properties/'.$property->id)
            ->assertSessionHas('error', 'This property is not accepting interest.');
    }

    public function test_b6_detail_and_marketplace_pages_receive_flash_error(): void
    {
        $property = $this->listing();

        $this->actingAs($this->tenant())->withSession(['error' => 'Something went wrong.'])
            ->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->where('flash.error', 'Something went wrong.'));

        $this->actingAs($this->tenant())->withSession(['error' => 'Something went wrong.'])
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('flash.error', 'Something went wrong.'));
    }

    // B7 — plain-English search

    public function test_b7_natural_language_parse_returns_leftover_keywords(): void
    {
        $parser = app(NaturalLanguageSearchService::class);

        $parsed = $parser->parse('3 bed flat in Borrowdale');
        $this->assertSame(3, $parsed['bedrooms']);
        $this->assertSame('flat', $parsed['property_type']);
        $this->assertSame('borrowdale', $parsed['keywords']);

        $parsed = $parser->parse('2 bedroom house in Harare under $700, unfurnished');
        $this->assertSame('house', $parsed['property_type']);
        $this->assertSame('Harare', $parsed['city']);
        $this->assertSame(700.0, $parsed['max_price']);
        $this->assertFalse($parsed['furnished']);
        $this->assertSame('', $parsed['keywords']);
    }

    public function test_b7_three_bed_flat_in_borrowdale_finds_the_borrowdale_flat(): void
    {
        $hit = $this->listing(['title' => 'Sunny garden unit', 'property_type' => 'flat', 'bedrooms' => 3, 'suburb' => 'Borrowdale']);
        $this->listing(['title' => 'Other flat', 'property_type' => 'flat', 'bedrooms' => 3, 'suburb' => 'Avondale']);
        $this->listing(['title' => 'Small flat', 'property_type' => 'flat', 'bedrooms' => 1, 'suburb' => 'Borrowdale']);

        $this->get('/?q='.urlencode('3 bed flat in borrowdale'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketplace/Index')
                ->has('properties', 1)
                ->where('properties.0.id', $hit->id));
    }

    public function test_b7_keywords_match_token_by_token_across_columns(): void
    {
        $hit = $this->listing(['title' => 'Quiet cottage', 'property_type' => 'cottage', 'suburb' => 'Glen Lorne', 'description' => 'Lovely jacaranda garden.']);

        $this->get('/?q='.urlencode('Glen Lorne, Harare'))
            ->assertInertia(fn ($page) => $page->where('properties', fn ($rows) => collect($rows)->pluck('id')->contains($hit->id)));

        $this->get('/?q='.urlencode('jacaranda lorne'))
            ->assertInertia(fn ($page) => $page->has('properties', 1)->where('properties.0.id', $hit->id));

        $this->get('/?q='.urlencode('jacaranda nowhereville'))
            ->assertInertia(fn ($page) => $page->has('properties', 0));
    }

    // B8 — move-in cost inputs

    public function test_b8_detail_page_exposes_price_and_deposit_for_the_move_in_cost(): void
    {
        $property = $this->listing(['price' => 750, 'deposit' => 750]);

        $this->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page
                ->where('property.price', fn ($price) => (float) $price === 750.0)
                ->where('property.deposit', fn ($deposit) => (float) $deposit === 750.0));
    }

    // B13 — off-market detail pages for related viewers only

    public function test_b13_off_market_home_is_404_for_guests_and_unrelated_users(): void
    {
        $property = $this->listing(['status' => 'reserved']);

        $this->get('/properties/'.$property->id)->assertNotFound();
        $this->actingAs($this->newUser('Tenant'))->get('/properties/'.$property->id)->assertNotFound();
        $this->actingAs($this->newUser('Owner'))->get('/properties/'.$property->id)->assertNotFound();
    }

    public function test_b13_owner_and_admins_see_their_off_market_home_read_only(): void
    {
        $property = $this->listing(['status' => 'occupied']);

        foreach ([$this->owner(), $this->admin(), $this->user('staff@dzimba.local')] as $viewer) {
            $this->actingAs($viewer)->get('/properties/'.$property->id)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Marketplace/Show')
                    ->where('property.id', $property->id)
                    ->where('onMarket', false));
        }

        $this->assertSame(0, PropertyView::where('property_id', $property->id)->count());
    }

    public function test_b13_related_tenants_see_the_off_market_home_without_actions(): void
    {
        $property = $this->listing(['status' => 'reserved']);
        $this->futureSlot($property);

        $links = [
            'lease' => fn (User $t) => Lease::create([
                'property_id' => $property->id, 'tenant_id' => $t->id, 'lease_no' => 'LSE-FIX-'.$t->id,
                'start_date' => now(), 'end_date' => now()->addYear(), 'rent_amount' => 500, 'deposit_amount' => 500,
                'payment_terms' => 'monthly', 'status' => 'active',
            ]),
            'application' => fn (User $t) => RentalApplication::create(['property_id' => $property->id, 'applicant_id' => $t->id, 'status' => 'pending']),
            'enquiry' => fn (User $t) => Enquiry::create(['property_id' => $property->id, 'tenant_id' => $t->id, 'message' => 'Hi']),
            'interest' => fn (User $t) => ExpressInterest::create(['property_id' => $property->id, 'tenant_id' => $t->id, 'status' => 'interested']),
            'favourite' => fn (User $t) => $t->favouritedProperties()->attach($property->id),
            'report' => fn (User $t) => Report::create(['reporter_id' => $t->id, 'subject_type' => 'property', 'subject_id' => $property->id, 'category' => 'already_rented', 'description' => 'Taken.']),
        ];

        foreach ($links as $relation => $link) {
            $tenant = $this->newUser('Tenant');
            $link($tenant);

            $this->actingAs($tenant)->get('/properties/'.$property->id)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('property.id', $property->id)
                    ->where('onMarket', false)
                    ->where('viewingSlots', null), $relation);
        }

        $this->assertSame(0, PropertyView::where('property_id', $property->id)->count());
    }

    public function test_b13_available_home_reports_on_market(): void
    {
        $property = $this->listing();

        $this->get('/properties/'.$property->id)->assertInertia(fn ($page) => $page->where('onMarket', true));
    }

    // B14 — re-apply after rejection

    public function test_b14_tenant_sees_rejection_and_can_apply_again(): void
    {
        $property = $this->listing();
        $tenant = $this->tenant();
        RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $tenant->id,
            'status' => 'rejected',
            'reject_reason' => 'Income too low for this rent.',
        ]);

        $this->actingAs($tenant)->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page
                ->where('applicationState.status', 'rejected')
                ->where('applicationState.reject_reason', 'Income too low for this rent.'));

        $this->inertiaPost($tenant, '/tenant/applications/'.$property->id, ['message' => 'Updated payslips attached.'], '/properties/'.$property->id)
            ->assertSessionHas('success');

        $this->actingAs($tenant)->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->where('applicationState.status', 'pending'));
    }

    // B15 — express-interest messages

    public function test_b15_express_interest_reports_new_reopened_and_already_active(): void
    {
        $property = $this->listing();
        $tenant = $this->tenant();
        $url = '/tenant/interests/'.$property->id;

        $this->inertiaPost($tenant, $url)->assertSessionHas('success', 'Interest recorded — the owner will be in touch.');
        $this->inertiaPost($tenant, $url)->assertSessionHas('success', 'You already expressed interest in this property.');

        ExpressInterest::where('property_id', $property->id)->where('tenant_id', $tenant->id)->update(['status' => 'archived']);

        $this->inertiaPost($tenant, $url)->assertSessionHas('success', 'Interest re-expressed — the owner has been notified again.');
        $this->assertDatabaseHas('express_interests', ['property_id' => $property->id, 'tenant_id' => $tenant->id, 'status' => 'interested']);
    }

    // B16 — dashboard Enquire button

    public function test_b16_dashboard_favourites_carry_status_and_enquiring_an_off_market_home_flashes_an_error(): void
    {
        $tenant = $this->tenant();
        $available = $this->listing(['title' => 'Still open']);
        $reserved = $this->listing(['title' => 'Already taken', 'status' => 'reserved']);
        $tenant->favouritedProperties()->attach([$available->id, $reserved->id]);

        $this->actingAs($tenant)->get('/tenant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Dashboard')
                ->where('favourites', fn ($rows) => collect($rows)->firstWhere('id', $reserved->id)['status'] === 'reserved'
                    && collect($rows)->firstWhere('id', $available->id)['status'] === 'available'));

        $this->inertiaPost($tenant, '/tenant/enquiries/'.$reserved->id, ['message' => 'Is this available?'], '/tenant')
            ->assertRedirect('/tenant')
            ->assertSessionHas('error');
    }

    // B17 — saved-search alert matching + per-search throttle

    public function test_b17_saved_search_matching_honours_marketplace_option_filters(): void
    {
        $service = app(SavedSearchService::class);
        $property = $this->listing([
            'payment_terms' => 'monthly', 'security_type' => 'gated', 'parking_type' => 'secure',
            'preferred_tenant' => 'family', 'available_from' => now()->addDays(10)->toDateString(),
            'title' => 'Family home near the park',
        ]);

        $this->assertTrue($service->matches($property, ['payment_terms' => 'monthly', 'security_type' => 'gated', 'parking_type' => 'secure', 'preferred_tenant' => 'family', 'availability' => 'upcoming']));
        $this->assertFalse($service->matches($property, ['payment_terms' => 'yearly']));
        $this->assertFalse($service->matches($property, ['security_type' => 'fenced']));
        $this->assertFalse($service->matches($property, ['parking_type' => 'street']));
        $this->assertFalse($service->matches($property, ['preferred_tenant' => 'students']));
        $this->assertFalse($service->matches($property, ['availability' => 'now']));
        $this->assertFalse($service->matches($property, ['furnished' => '1']));
        $this->assertTrue($service->matches($property, ['furnished' => '0']));
        $this->assertTrue($service->matches($property, ['q' => 'park family']));
        $this->assertFalse($service->matches($property, ['q' => 'penthouse']));
    }

    public function test_b17_match_alerts_are_throttled_per_saved_search(): void
    {
        $tenant = $this->newUser('Tenant');
        $houses = SavedSearch::create(['user_id' => $tenant->id, 'name' => 'Houses', 'criteria' => ['property_type' => 'house'], 'notify' => true]);
        $avondale = SavedSearch::create(['user_id' => $tenant->id, 'name' => 'Avondale', 'criteria' => ['suburb' => 'Avondale'], 'notify' => true]);
        $alerts = app(MarketplaceAlertService::class);

        $alerts->notifyNewMatches($this->listing(['property_type' => 'house', 'suburb' => 'Borrowdale']));
        $alerts->notifyNewMatches($this->listing(['property_type' => 'flat', 'suburb' => 'Avondale']));

        $sent = fn () => $tenant->notifications()->where('type', SavedSearchMatchNotification::class)->get();
        $this->assertCount(2, $sent(), 'Each saved search gets its own alert.');

        $alerts->notifyNewMatches($this->listing(['property_type' => 'house', 'suburb' => 'Avondale']));
        $this->assertCount(2, $sent(), 'Both searches already alerted within 24h.');

        $this->travel(25)->hours();
        $alerts->notifyNewMatches($this->listing(['property_type' => 'house', 'suburb' => 'Mabelreign']));
        $this->assertCount(3, $sent(), 'The houses search alerts again after 24h.');
        $this->assertTrue($sent()->contains(fn ($n) => str_contains($n->data['title'], $houses->name)));
        $this->assertTrue($sent()->contains(fn ($n) => str_contains($n->data['title'], $avondale->name)));
    }

    // B18 — price formatting inputs

    public function test_b18_marketplace_exposes_currency_and_payment_terms_for_price_labels(): void
    {
        $this->listing(['currency' => 'ZWL', 'payment_terms' => 'quarterly', 'title' => 'Quarterly Qzxv flat']);

        $this->get('/?q=Qzxv')->assertInertia(fn ($page) => $page
            ->where('properties.0.currency', 'ZWL')
            ->where('properties.0.payment_terms', 'quarterly'));
    }

    // B19 — featured showcase only for featured listings

    public function test_b19_no_featured_listings_means_an_empty_featured_rail(): void
    {
        Property::query()->update(['featured' => false]);
        $this->listing();

        $this->get('/')->assertInertia(fn ($page) => $page->has('featured', 0)->where('properties.0.featured', false));
    }

    public function test_b19_featured_rail_holds_only_featured_listings(): void
    {
        Property::query()->update(['featured' => false]);
        $featured = $this->listing(['featured' => true]);
        $this->listing();

        $this->get('/')->assertInertia(fn ($page) => $page->has('featured', 1)->where('featured.0.id', $featured->id));
    }

    // B20 — quick view toggle

    public function test_b20_quick_view_setting_is_passed_to_the_marketplace(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page->where('marketplace.quickViewEnabled', true));

        app(ConfigurationService::class)->set('marketplace.quick_view_enabled', false, $this->admin()->id, 'Disable quick view.', $this->admin()->id);

        $this->get('/')->assertInertia(fn ($page) => $page->where('marketplace.quickViewEnabled', false));
    }

    // B22 — suspended owners hidden everywhere on the public marketplace

    public function test_b22_hidden_suspended_listings_do_not_leak_through_any_marketplace_surface(): void
    {
        $hidden = $this->suspendedOwnerListing();
        $excludes = fn ($rows) => ! collect($rows)->pluck('id')->contains($hidden->id);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('properties', $excludes)
            ->where('featured', $excludes)
            ->where('justListed', $excludes)
            ->where('cities', fn ($cities) => ! collect($cities)->contains('Qwertytown'))
            ->where('zones', fn ($zones) => ! collect($zones)->contains('Qwerty Zone'))
            ->where('explore', fn ($places) => ! collect($places)->pluck('suburb')->contains('Qwertyville')));

        $this->get('/search/suggestions?q=Qwerty')->assertOk()->assertExactJson([]);
        $this->get('/search/suggestions?q=Zyxwv')->assertOk()->assertExactJson([]);

        PropertyView::create(['property_id' => $hidden->id, 'user_id' => null, 'ip' => '127.0.0.1', 'viewed_at' => now()]);
        $tenant = $this->newUser('Tenant');
        $this->actingAs($tenant)->get('/')->assertInertia(fn ($page) => $page->where('recommended', $excludes));

        $this->get('/properties/'.$hidden->id)->assertNotFound();
    }

    public function test_b22_similar_rail_excludes_hidden_suspended_listings(): void
    {
        $hidden = $this->suspendedOwnerListing(['city' => 'Harare', 'property_type' => 'house']);
        $property = $this->listing(['property_type' => 'house', 'city' => 'Harare']);

        $this->get('/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->where('similar', fn ($rows) => ! collect($rows)->pluck('id')->contains($hidden->id)));
    }
}
