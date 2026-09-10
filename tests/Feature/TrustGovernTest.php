<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marketplace trust & governance: listing reports, their moderation queue
 * and the marketplace analytics surfaces (Property Marketplace §35/§44).
 */
class TrustGovernTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function owner(): User
    {
        return User::where('username', 'owner')->first();
    }

    private function listed(): Property
    {
        return Property::where('status', 'available')->firstOrFail();
    }

    private function reportPayload(Property $property, array $overrides = []): array
    {
        return array_merge([
            'subject_type' => 'property',
            'subject_id' => $property->id,
            'category' => 'suspicious_listing',
            'description' => 'The photos do not match the address shown.',
            'priority' => 'medium',
        ], $overrides);
    }

    public function test_guest_can_report_a_listing_publicly(): void
    {
        $property = $this->listed();

        $this->post('/properties/'.$property->id.'/report', $this->reportPayload($property))
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => null,
            'subject_type' => 'property',
            'subject_id' => $property->id,
            'category' => 'suspicious_listing',
            'status' => 'open',
            'priority' => 'medium',
        ]);
    }

    public function test_a_report_requires_category_description_and_subject(): void
    {
        $property = $this->listed();

        $this->post('/properties/'.$property->id.'/report', [
            'subject_type' => 'property',
            'subject_id' => $property->id,
        ])->assertSessionHasErrors(['category', 'description']);

        $this->post('/properties/'.$property->id.'/report', $this->reportPayload($property, [
            'category' => 'made_up_reason',
        ]))->assertSessionHasErrors('category');

        $this->assertDatabaseCount('reports', 1); // only the seeded demo report
    }

    public function test_a_report_against_a_missing_property_is_not_created(): void
    {
        $property = $this->listed();

        $this->post('/properties/99999/report', $this->reportPayload($property, ['subject_id' => 99999]))
            ->assertNotFound();
    }

    public function test_tenant_reports_page_lists_only_own_reports(): void
    {
        $tenant = $this->tenant();
        $stranger = User::factory()->create(['name' => 'Reporter Stranger', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $property = $this->listed();

        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property, [
            'category' => 'wrong_price',
            'description' => 'Stale rent.',
        ]))->assertRedirect();

        $this->actingAs($stranger)->post('/properties/'.$property->id.'/report', $this->reportPayload($property, [
            'category' => 'duplicate',
            'description' => 'Listed twice.',
        ]))->assertRedirect();
        $strangerReport = Report::where('description', 'Listed twice.')->firstOrFail();

        $page = $this->actingAs($tenant)->get('/tenant/reports')->assertOk()->viewData('page');

        $ids = array_column($page['props']['reports'], 'id');
        $this->assertNotContains($strangerReport->id, $ids, 'Tenant should never see another reporter\'s reports.');
        $myIds = Report::where('reporter_id', $tenant->id)->pluck('id')->sort()->values()->all();
        $this->assertSame($myIds, collect($ids)->sort()->values()->all(), 'Tenant sees exactly their own filed reports.');
    }

    public function test_admin_moderation_queue_groups_open_reports_in_stats(): void
    {
        $tenant = $this->tenant();
        $property = $this->listed();

        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property, [
            'description' => 'First open report.',
        ]))->assertRedirect();
        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property, [
            'description' => 'Second open report.',
        ]))->assertRedirect();

        $report = Report::where('description', 'First open report.')->firstOrFail();
        $report->update(['status' => 'under_review']);

        $page = $this->actingAs($this->admin())
            ->get('/admin/marketplace/reports')
            ->assertOk()
            ->viewData('page');

        $props = $page['props'];
        $this->assertSame(3, $props['stats']['open'], 'Open + under review reports bucket together.');
        $this->assertSame(Report::count(), array_sum($props['stats']), 'The buckets cover every report in the queue.');
    }

    public function test_admin_can_resolve_a_report_with_a_note(): void
    {
        $tenant = $this->tenant();
        $property = $this->listed();

        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property));

        $report = Report::where('reporter_id', $tenant->id)->firstOrFail();
        $this->assertSame('open', $report->status);

        $this->actingAs($this->admin())
            ->post('/admin/marketplace/reports/'.$report->id.'/resolved', ['note' => 'Verified the address is genuine.'])
            ->assertRedirect();

        $fresh = $report->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame($this->admin()->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame('Verified the address is genuine.', $fresh->resolution_note);
    }

    public function test_reports_follow_an_explicit_state_machine(): void
    {
        $tenant = $this->tenant();
        $property = $this->listed();
        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property));

        $report = Report::where('reporter_id', $tenant->id)->firstOrFail();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/marketplace/reports/'.$report->id.'/escalated')
            ->assertSessionHasErrors('status');
        $this->assertSame('open', $report->fresh()->status);

        $this->actingAs($admin)->post('/admin/marketplace/reports/'.$report->id.'/under_review')->assertRedirect();
        $this->actingAs($admin)->post('/admin/marketplace/reports/'.$report->id.'/escalated')->assertRedirect();
        $this->assertSame('escalated', $report->fresh()->status);

        $this->actingAs($admin)->post('/admin/marketplace/reports/'.$report->id.'/resolved')->assertRedirect();
        $this->assertSame('resolved', $report->fresh()->status);

        $this->actingAs($admin)
            ->post('/admin/marketplace/reports/'.$report->id.'/open')
            ->assertSessionHasErrors('status');
        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_resolving_a_reported_property_can_take_the_listing_down(): void
    {
        $tenant = $this->tenant();
        $property = $this->listed();

        $this->actingAs($tenant)->post('/properties/'.$property->id.'/report', $this->reportPayload($property));

        $report = Report::where('reporter_id', $tenant->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/marketplace/reports/'.$report->id.'/resolved', [
                'note' => 'Confirmed fraudulent listing.',
                'hide_listing' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('unavailable', $property->fresh()->status);
        $this->assertDatabaseHas('property_history', [
            'property_id' => $property->id,
            'from_status' => 'available',
            'to_status' => 'unavailable',
            'note' => 'Listing removed after resolved report',
        ]);
    }

    public function test_owner_analytics_page_reports_own_portfolio_performance(): void
    {
        $owner = User::factory()->create(['name' => 'Analytics Owner', 'password_changed_at' => now()]);
        $owner->roles()->attach(Role::where('name', 'Owner')->first()->id);

        $a = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Analytics House A',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 800,
            'status' => 'available',
            'suburb' => 'Eastlea',
            'city' => 'Harare',
        ]);
        $b = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Analytics House B',
            'property_type' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'status' => 'occupied',
            'suburb' => 'Eastlea',
            'city' => 'Harare',
        ]);
        \App\Models\PropertyView::create([
            'property_id' => $a->id,
            'user_id' => $this->tenant()->id,
            'viewed_at' => now(),
        ]);

        $page = $this->actingAs($owner)->get('/owner/analytics')->assertOk()->viewData('page');

        $props = $page['props']['analytics'];
        $this->assertSame(2, $props['totals']['properties']);
        $this->assertSame(1, $props['totals']['listed']);
        $this->assertSame(1, $props['totals']['occupied']);
        $this->assertSame($a->id, $props['topProperty']['id']);
        $this->assertCount(2, $props['properties']);
        $this->assertTrue(collect($props['properties'])->every(fn ($p) => in_array($p['id'], [$a->id, $b->id], true)));
    }

    public function test_owner_analytics_never_leaks_other_owners_properties(): void
    {
        $ownerA = User::factory()->create(['name' => 'Analytics A', 'password_changed_at' => now()]);
        $ownerA->roles()->attach(Role::where('name', 'Owner')->first()->id);
        $ownerB = User::factory()->create(['name' => 'Analytics B', 'password_changed_at' => now()]);
        $ownerB->roles()->attach(Role::where('name', 'Owner')->first()->id);

        $mine = Property::create([
            'owner_id' => $ownerA->id,
            'title' => 'Only mine',
            'property_type' => 'cottage',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price' => 300,
            'status' => 'available',
            'suburb' => 'Chisipite',
            'city' => 'Harare',
        ]);
        Property::create([
            'owner_id' => $ownerB->id,
            'title' => 'Not yours',
            'property_type' => 'house',
            'bedrooms' => 4,
            'bathrooms' => 3,
            'price' => 1500,
            'status' => 'available',
            'suburb' => 'Chisipite',
            'city' => 'Harare',
        ]);

        $page = $this->actingAs($ownerA)->get('/owner/analytics')->assertOk()->viewData('page');

        $ids = array_column($page['props']['analytics']['properties'], 'id');
        $this->assertSame([$mine->id], $ids, 'Owner analytics must only surface the owner\'s own properties.');
    }

    public function test_admin_marketplace_analytics_renders_platform_totals(): void
    {
        $this->listed(); // rely on seeded listings

        $page = $this->actingAs($this->admin())
            ->get('/admin/marketplace/analytics')
            ->assertOk()
            ->viewData('page');

        $props = $page['props']['analytics'];
        $this->assertArrayHasKey('totals', $props);
        $this->assertArrayHasKey('trend', $props);
        $this->assertCount(30, $props['trend']);
        $this->assertGreaterThan(0, $props['totals']['listed']);
    }
}