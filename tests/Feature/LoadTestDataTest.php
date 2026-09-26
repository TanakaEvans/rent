<?php

namespace Tests\Feature;

use App\Console\Commands\SeedDemoDataCommand;
use App\Models\Property;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The load-test generator must produce data the app itself would accept, so
 * performance numbers reflect real behaviour rather than broken rows.
 */
class LoadTestDataTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = '%@'.SeedDemoDataCommand::EMAIL_DOMAIN;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function generate(array $options = []): void
    {
        $this->artisan('zimrent:seed-demo', array_merge(['--owners' => 6, '--tenants' => 40, '--properties' => 60], $options))
            ->assertSuccessful();
    }

    public function test_it_creates_owners_tenants_and_listings_with_real_photos(): void
    {
        $this->generate();

        $owners = User::where('email', 'like', 'owner%'.substr(self::DOMAIN, 1))->get();
        $this->assertCount(6, $owners);
        $this->assertTrue($owners->every(fn (User $u) => $u->hasRole('Owner')));
        $this->assertSame(40, User::where('email', 'like', 'tenant%'.substr(self::DOMAIN, 1))->count());

        $listings = Property::whereIn('owner_id', $owners->pluck('id'))->with('images')->get();
        $this->assertGreaterThanOrEqual(60, $listings->count());
        foreach ($listings as $listing) {
            $this->assertNotEmpty($listing->images, "Listing {$listing->id} has no photos.");
            $this->assertStringStartsWith('https://images.unsplash.com/', $listing->cover_image);
            $this->assertSame($listing->images->first()->path, $listing->cover_image);
            $this->assertNotNull($listing->latitude);
            $this->assertContains($listing->status, ['available', 'unavailable']);
        }
    }

    public function test_generated_owners_stay_within_their_plan_listing_limit(): void
    {
        $this->generate();

        $subscriptions = app(SubscriptionService::class);
        foreach (User::where('email', 'like', 'owner%'.substr(self::DOMAIN, 1))->get() as $owner) {
            $limit = $subscriptions->ensureFor($owner)->plan->listing_limit;
            $available = Property::where('owner_id', $owner->id)->where('status', 'available')->count();
            if ($limit !== null) {
                $this->assertLessThanOrEqual($limit, $available, "{$owner->email} exceeds its plan limit.");
            }
        }
    }

    public function test_activity_respects_the_marketplace_rules(): void
    {
        $this->generate(['--tenants' => 120, '--properties' => 80]);

        $duplicate = fn (string $table, string $tenantColumn) => DB::table($table)
            ->select('property_id', $tenantColumn)
            ->groupBy('property_id', $tenantColumn)
            ->havingRaw('count(*) > 1')
            ->exists();

        $this->assertFalse($duplicate('enquiries', 'tenant_id'), 'A tenant has two enquiries on one listing.');
        $this->assertFalse($duplicate('express_interests', 'tenant_id'), 'A tenant has two interests on one listing.');
        $this->assertFalse($duplicate('rental_applications', 'applicant_id'), 'A tenant has two applications on one listing.');

        $this->assertFalse(DB::table('rental_applications')->where('status', 'approved')
            ->select('property_id')->groupBy('property_id')->havingRaw('count(*) > 1')->exists(),
            'A listing has more than one approved application.');

        $this->assertFalse(DB::table('viewing_requests')->where('viewing_requests.status', 'accepted')
            ->join('viewing_slots', 'viewing_slots.id', '=', 'viewing_requests.slot_id')
            ->where('viewing_slots.status', '!=', 'taken')->exists(),
            'An accepted viewing points at a slot that is not locked.');

        $generatedTenants = User::where('email', 'like', self::DOMAIN)->pluck('id');
        $unavailable = Property::where('status', '!=', 'available')->pluck('id');
        foreach (['enquiries' => 'tenant_id', 'express_interests' => 'tenant_id', 'rental_applications' => 'applicant_id'] as $table => $column) {
            $this->assertSame(0, DB::table($table)->whereIn($column, $generatedTenants)->whereIn('property_id', $unavailable)->count(),
                "Generated {$table} must only target available listings.");
        }
    }

    public function test_generated_pages_render_for_each_role(): void
    {
        $this->generate();

        $listing = Property::where('status', 'available')->whereIn('owner_id', User::where('email', 'like', self::DOMAIN)->pluck('id'))->firstOrFail();
        $tenant = User::where('email', 'tenant1@'.SeedDemoDataCommand::EMAIL_DOMAIN)->firstOrFail();

        $this->get('/')->assertOk();
        $this->get(route('property.show', $listing->id))->assertOk();
        $this->actingAs($tenant)->get(route('tenant.dashboard'))->assertOk();
        $this->actingAs($listing->owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($listing->owner)->get(route('owner.properties.index'))->assertOk();
    }

    public function test_it_refuses_to_duplicate_and_fresh_replaces_the_data_set(): void
    {
        $this->generate();
        $before = User::where('email', 'like', self::DOMAIN)->count();

        $this->artisan('zimrent:seed-demo', ['--owners' => 6, '--tenants' => 40, '--properties' => 60])->assertFailed();
        $this->assertSame($before, User::where('email', 'like', self::DOMAIN)->count());

        $this->generate(['--fresh' => true, '--owners' => 3, '--tenants' => 10, '--properties' => 20]);
        $this->assertSame(13, User::where('email', 'like', self::DOMAIN)->count());
        $this->assertSame(0, DB::table('reports')->whereNotIn('subject_id', Property::pluck('id'))->where('subject_type', 'property')->count());
    }
}
