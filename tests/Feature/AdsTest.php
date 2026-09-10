<?php

namespace Tests\Feature;

use App\Models\AdEvent;
use App\Models\AdPackage;
use App\Models\AdPlacement;
use App\Models\Property;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Notifications\AdPlacementActivatedNotification;
use App\Notifications\AdPlacementCancelledNotification;
use App\Services\AdPlacementService;
use App\Services\ApplicationService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function service(): AdPlacementService
    {
        return app(AdPlacementService::class);
    }

    private function owner(): User
    {
        return User::where('username', 'owner')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->first();
    }

    private function newOwner(): User
    {
        $user = User::factory()->create(['name' => 'Fresh Owner', 'email' => 'fresh-ad-owner@example.test', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Owner')->first()->id);

        return $user;
    }

    private function availableProperty(User $owner, string $status = 'available'): Property
    {
        return Property::create([
            'owner_id' => $owner->id,
            'title' => 'Ad Test House '.Str::random(6),
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => $status,
            'suburb' => 'Borrowdale',
            'city' => 'Harare',
        ]);
    }

    private function package(): AdPackage
    {
        return AdPackage::where('code', 'featured-property')->firstOrFail();
    }

    private function updateConfig(string $key, string $value): void
    {
        SystemConfiguration::where('key', $key)->update(['value' => $value]);
        Cache::forget('dzimba.config.'.$key);
    }

    // ---------- booking (FR-01/FR-02) ----------

    public function test_booking_creates_a_reserved_placement_snapshot(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $placement = $this->service()->book($owner, $property, $this->package());

        $this->assertSame('reserved', $placement->status);
        $this->assertSame('10.00', $placement->amount);
        $this->assertSame('0.00', $placement->credit_amount);
        $this->assertNull($placement->starts_at);
        $this->assertNull($placement->paid_at);
        $this->assertFalse($property->fresh()->featured);
    }

    public function test_booking_is_blocked_when_promotions_are_disabled(): void
    {
        $this->updateConfig('featured.enabled', '0');
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $this->expectException(ValidationException::class);
        $this->service()->book($owner, $property, $this->package());
    }

    public function test_cannot_promote_another_owners_property(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($this->owner());

        try {
            $this->service()->book($owner, $property, $this->package());
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('property_id', $e->errors());
        }
    }

    public function test_only_an_available_listing_can_be_promoted(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner, 'occupied');

        $this->expectException(ValidationException::class);
        $this->service()->book($owner, $property, $this->package());
    }

    public function test_a_property_holds_a_single_placement_slot(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $this->service()->book($owner, $property, $this->package());

        $this->expectException(ValidationException::class);
        $this->service()->book($owner, $property, $this->package());
    }

    public function test_booking_honours_the_owner_spend_cap(): void
    {
        $this->updateConfig('featured.max_active_per_owner', '1');
        $owner = $this->newOwner();
        $first = $this->availableProperty($owner);
        $second = $this->availableProperty($owner, 'available');

        $placed = $this->service()->book($owner, $first, $this->package());

        $this->expectException(ValidationException::class);
        $this->service()->book($owner, $second, $this->package());
    }

    public function test_booking_activates_immediately_when_approval_is_not_required(): void
    {
        $this->updateConfig('featured.approval_required', '0');

        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $placement = $this->service()->book($owner, $property, $this->package());

        $this->assertSame('active', $placement->status);
        $this->assertNotNull($placement->paid_at);
        $this->assertTrue($property->fresh()->featured);
    }

    // ---------- approval & the window (FR-02) ----------

    public function test_admin_approval_settles_and_opens_the_promotion_window(): void
    {
        Notification::fake();

        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());

        $activated = $this->service()->approve($placement);

        $this->assertSame('active', $activated->status);
        $this->assertNotNull($activated->starts_at);
        $this->assertNotNull($activated->paid_at);
        $this->assertTrue($activated->ends_at->greaterThanOrEqualTo(now()->addDays(30)->subMinute()));
        $this->assertTrue($property->fresh()->featured);

        Notification::assertSentTo($owner, AdPlacementActivatedNotification::class);
    }

    public function test_approval_only_applies_to_reserved_placements(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());

        $this->service()->approve($placement);

        $this->expectException(ValidationException::class);
        $this->service()->approve($placement->fresh());
    }

    public function test_cancelling_a_reserved_order_credits_in_full_and_notifies(): void
    {
        Notification::fake();

        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());

        $cancelled = $this->service()->cancel($placement, 'Owner changed mind');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('10.00', $cancelled->credit_amount);
        $this->assertSame('Owner changed mind', $cancelled->admin_note);
        $this->assertFalse($property->fresh()->featured);

        Notification::assertSentTo($owner, AdPlacementCancelledNotification::class);
    }

    public function test_cancelling_an_active_window_prorates_the_unused_portion(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);

        $this->travel(10)->days();

        $cancelled = $this->service()->cancel($placement->fresh());

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('6.66', $cancelled->credit_amount);
        $this->assertFalse($property->fresh()->featured);
    }

    public function test_terminal_placements_reject_further_actions(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);
        $this->service()->cancel($placement->fresh());

        $this->expectException(ValidationException::class);
        $this->service()->cancel($placement->fresh());
    }

    // ---------- moderation: pause / resume / expiry (NFR-01) ----------

    public function test_paused_placements_are_frozen_and_resuming_extends_the_window(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);
        $originalEnd = $placement->fresh()->ends_at;

        $paused = $this->service()->pause($placement->fresh());
        $this->assertSame('paused', $paused->status);
        $this->assertFalse($property->fresh()->featured);

        $this->travel(5)->days();

        $this->assertSame(0, $this->service()->expireDue());
        $this->assertSame('paused', $placement->fresh()->status);

        $resumed = $this->service()->resume($placement->fresh());
        $this->assertSame('active', $resumed->status);
        $this->assertNull($resumed->paused_at);
        $this->assertGreaterThanOrEqual(5 * 86400 - 120, $resumed->ends_at->diffInSeconds($originalEnd));
        $this->assertTrue($property->fresh()->featured);
    }

    public function test_expiry_sweep_flags_expired_windows_and_allows_rebooking(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);

        $this->travel(31)->days();

        $this->assertGreaterThanOrEqual(1, $this->service()->expireDue());
        $this->assertSame('expired', $placement->fresh()->status);
        $this->assertFalse($property->fresh()->featured);

        $rebooked = $this->service()->book($owner, $property->fresh(), $this->package());
        $this->assertSame('reserved', $rebooked->status);
    }

    // ---------- attribution stats (FR-05) ----------

    public function test_events_power_placement_stats(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);

        $this->service()->recordImpressionsFor(collect([$property]));
        $this->service()->trackForProperty($property, 'click');
        app(EnquiryService::class)->create($this->tenant(), $property, ['message' => 'Is it still available?']);
        app(ApplicationService::class)->create($this->tenant(), $property, 'I would like to apply.');

        $stats = $this->service()->statsFor($placement->fresh());

        $this->assertSame(['impressions' => 1, 'clicks' => 1, 'enquiries' => 1, 'applications' => 1], $stats);
        $this->assertSame(4, AdEvent::count());
    }

    // ---------- HTTP: owner + admin flows ----------

    public function test_owner_can_view_the_advertising_page(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $this->actingAs($owner)->get('/owner/advertising')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Advertising')
                ->has('packages')
                ->where('enabled', true)
                ->where('approval_required', true)
                ->has('placements', 0)
                ->has('properties', 1));
    }

    public function test_owner_can_book_a_promotion(): void
    {
        Notification::fake();

        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);

        $response = $this->actingAs($owner)
            ->post('/owner/advertising', ['property_id' => $property->id, 'package_id' => $this->package()->id]);

        $response->assertRedirect(route('owner.advertising.index'));
        $this->assertDatabaseHas('ad_placements', [
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'status' => 'reserved',
            'amount' => '10.00',
        ]);
    }

    public function test_owner_cannot_book_another_owners_property(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($this->owner());

        $this->actingAs($owner)
            ->post('/owner/advertising', ['property_id' => $property->id, 'package_id' => $this->package()->id])
            ->assertNotFound();
    }

    public function test_owner_cannot_promote_an_unavailable_listing(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner, 'occupied');

        $this->actingAs($owner)
            ->post('/owner/advertising', ['property_id' => $property->id, 'package_id' => $this->package()->id])
            ->assertSessionHasErrors('property_id');
    }

    public function test_admin_can_view_the_advertising_queue(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/advertising')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Advertising'));
    }

    public function test_admin_can_approve_a_reserved_order(): void
    {
        $owner = $this->newOwner();
        $placement = $this->service()->book($owner, $this->availableProperty($owner), $this->package());

        $this->actingAs($this->admin())
            ->post(route('admin.advertising.approve', ['placement' => $placement->id]))
            ->assertRedirect();

        $this->assertSame('active', $placement->fresh()->status);
        $this->assertNotNull($placement->fresh()->paid_at);
    }

    public function test_admin_can_cancel_a_live_placement_with_a_note(): void
    {
        $owner = $this->newOwner();
        $property = $this->availableProperty($owner);
        $placement = $this->service()->book($owner, $property, $this->package());
        $this->service()->approve($placement);

        $this->actingAs($this->admin())
            ->post(route('admin.advertising.cancel', ['placement' => $placement->id]), ['note' => 'Listing pulled for maintenance'])
            ->assertRedirect();

        $this->assertSame('cancelled', $placement->fresh()->status);
        $this->assertSame('Listing pulled for maintenance', $placement->fresh()->admin_note);
        $this->assertFalse($property->fresh()->featured);
    }

    // ---------- seeding (demo data stays consistent) ----------

    public function test_seeder_creates_packages_and_demo_placements(): void
    {
        $this->assertSame(4, AdPackage::count());
        $this->assertTrue(AdPackage::where('code', 'featured-property')->exists());

        $active = AdPlacement::where('status', 'active')->count();
        $reserved = AdPlacement::where('status', 'reserved')->count();
        $this->assertGreaterThanOrEqual(1, $active);
        $this->assertGreaterThanOrEqual(1, $reserved);
    }
}