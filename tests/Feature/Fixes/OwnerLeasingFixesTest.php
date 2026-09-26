<?php

namespace Tests\Feature\Fixes;

use App\Models\Enquiry;
use App\Models\ExpressInterest;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\RentalApplication;
use App\Models\Role;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Notifications\EnquiryTenantRepliedNotification;
use App\Notifications\LeaseTerminatedNotification;
use App\Notifications\ListingExpiryReminderNotification;
use App\Notifications\ViewingCancelledNotification;
use App\Notifications\ViewingConfirmedNotification;
use App\Notifications\ViewingDeclinedNotification;
use App\Services\ListingLifecycleService;
use App\Services\RentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for the owner listings / viewings / enquiries /
 * applications / leases fixes.
 */
class OwnerLeasingFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    private function owner(): User
    {
        return User::where('email', 'owner@dzimba.local')->first();
    }

    private function tenant(): User
    {
        return User::where('email', 'tenant@dzimba.local')->first();
    }

    private function newUser(string $role, string $name = 'Fixture User'): User
    {
        $user = User::factory()->create(['name' => $name, 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->first()->id);

        return $user;
    }

    private function makeProperty(?User $owner = null, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'owner_id' => ($owner ?? $this->owner())->id,
            'title' => 'Fixture Home '.mt_rand(1000, 9999),
            'description' => 'A listing created for the fixes suite.',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 900.00,
            'deposit' => 900.00,
            'currency' => 'USD',
            'payment_terms' => 'monthly',
            'status' => 'available',
            'suburb' => 'Fixture Suburb',
            'city' => 'Harare',
            'security_type' => 'fenced',
            'minimum_stay' => 12,
            'preferred_tenant' => 'any',
            'landlord_type' => 'direct',
            'contact_preference' => 'platform',
            'expires_at' => now()->addDays(30),
        ], $overrides));
    }

    private function listingPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fixes Suite House',
            'description' => 'Bright and secure.',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 800,
            'deposit' => 800,
            'currency' => 'USD',
            'payment_terms' => 'monthly',
            'security_type' => 'fenced',
            'minimum_stay' => 12,
            'preferred_tenant' => 'any',
            'landlord_type' => 'direct',
            'contact_preference' => 'platform',
            'suburb' => 'Avondale',
            'city' => 'Harare',
        ], $overrides);
    }

    private function approvedApplication(Property $property, ?User $tenant = null): RentalApplication
    {
        return RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => ($tenant ?? $this->tenant())->id,
            'message' => 'Please consider me.',
            'status' => 'approved',
            'reviewed_by' => $property->owner_id,
        ]);
    }

    private function lease(Property $property, string $status, ?User $tenant = null, array $overrides = []): Lease
    {
        return Lease::create(array_merge([
            'property_id' => $property->id,
            'tenant_id' => ($tenant ?? $this->tenant())->id,
            'lease_no' => 'LSE-FIX-'.mt_rand(10000, 99999),
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(9)->toDateString(),
            'rent_amount' => 900,
            'deposit_amount' => 900,
            'currency' => 'USD',
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1],
            'status' => $status,
            'clause_version' => 1,
        ], $overrides));
    }

    private function slot(Property $property, int $daysAhead = 3, string $status = 'available'): ViewingSlot
    {
        return ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays($daysAhead)->setTime(10, 0),
            'ends_at' => now()->addDays($daysAhead)->setTime(11, 0),
            'status' => $status,
        ]);
    }

    private function booking(ViewingSlot $slot, string $status = 'requested'): ViewingRequest
    {
        return ViewingRequest::create([
            'property_id' => $slot->property_id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'status' => $status,
        ]);
    }

    // ---- 1. Edit never changes status -------------------------------------------

    public function test_editing_a_listing_ignores_status(): void
    {
        $property = $this->makeProperty();
        $expiresAt = $property->expires_at->toDateTimeString();

        $this->actingAs($this->owner())
            ->put('/owner/properties/'.$property->id, $this->listingPayload(['status' => 'occupied', 'title' => 'Renamed']))
            ->assertRedirect(route('owner.properties.show', $property->id))
            ->assertSessionHasNoErrors();

        $fresh = $property->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertSame('available', $fresh->status);
        $this->assertSame($expiresAt, $fresh->expires_at->toDateTimeString());
        $this->assertDatabaseMissing('property_history', ['property_id' => $property->id]);
    }

    // ---- 2. Photos on edit --------------------------------------------------------

    public function test_edit_via_post_method_override_uploads_new_photos(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->listingPayload([
                'status' => 'unavailable',
                'images' => [UploadedFile::fake()->image('one.jpg')],
            ]))
            ->assertSessionHasNoErrors();
        $property = Property::where('title', 'Fixes Suite House')->firstOrFail();
        $existing = $property->images()->first()->path;

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$property->id, $this->listingPayload([
                '_method' => 'put',
                'cover_image' => $property->cover_image,
                'images' => [$existing, UploadedFile::fake()->image('two.jpg')],
            ]))
            ->assertRedirect(route('owner.properties.show', $property->id))
            ->assertSessionHasNoErrors();

        $paths = $property->images()->pluck('path')->all();
        $this->assertCount(2, $paths);
        $this->assertSame($existing, $paths[0]);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $path), '/'));
        }
    }

    public function test_edit_can_remove_gallery_photos_without_uploading(): void
    {
        $property = $this->makeProperty(null, ['cover_image' => '/uploads/cover.jpg']);
        foreach (['/uploads/a.jpg', '/uploads/b.jpg', '/uploads/c.jpg'] as $sort => $path) {
            PropertyImage::create(['property_id' => $property->id, 'path' => $path, 'caption' => 'x', 'sort_order' => $sort]);
        }

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$property->id, $this->listingPayload([
                '_method' => 'put',
                'cover_image' => '/uploads/cover.jpg',
                'images' => ['/uploads/c.jpg', '/uploads/a.jpg'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['/uploads/c.jpg', '/uploads/a.jpg'], $property->images()->pluck('path')->all());
        $this->assertSame('/uploads/cover.jpg', $property->fresh()->cover_image);
    }

    public function test_edit_cannot_remove_every_photo(): void
    {
        $property = $this->makeProperty(null, ['cover_image' => '/uploads/cover.jpg']);
        PropertyImage::create(['property_id' => $property->id, 'path' => '/uploads/a.jpg', 'caption' => 'x', 'sort_order' => 0]);

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$property->id, $this->listingPayload([
                '_method' => 'put',
                'cover_image' => '',
            ]))
            ->assertSessionHasErrors('media');

        $this->assertSame(1, $property->images()->count());
        $this->assertSame('/uploads/cover.jpg', $property->fresh()->cover_image);
    }

    public function test_edit_replaces_the_cover_and_deletes_only_the_unused_stored_file(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->listingPayload([
                'status' => 'unavailable',
                'cover' => UploadedFile::fake()->image('cover.jpg'),
                'images' => [UploadedFile::fake()->image('gallery.jpg')],
            ]))
            ->assertSessionHasNoErrors();
        $property = Property::where('title', 'Fixes Suite House')->firstOrFail();
        $oldCover = $property->cover_image;
        $gallery = $property->images()->first()->path;

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$property->id, $this->listingPayload([
                '_method' => 'put',
                'cover' => UploadedFile::fake()->image('new-cover.jpg'),
                'cover_image' => $oldCover,
                'images' => [$gallery],
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $property->fresh();
        $this->assertNotSame($oldCover, $fresh->cover_image);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $fresh->cover_image), '/'));
        Storage::disk('public')->assertMissing(ltrim(str_replace('/storage/', '', $oldCover), '/'));
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $gallery), '/'));
    }

    public function test_edit_ignores_photo_paths_that_do_not_belong_to_the_listing(): void
    {
        $property = $this->makeProperty(null, ['cover_image' => '/uploads/cover.jpg']);

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$property->id, $this->listingPayload([
                '_method' => 'put',
                'cover_image' => '/storage/properties/999/someone-else.jpg',
                'images' => ['/storage/properties/999/someone-else.jpg'],
            ]))
            ->assertSessionHasErrors('media');

        $this->assertSame('/uploads/cover.jpg', $property->fresh()->cover_image);
    }

    public function test_create_without_any_photo_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/properties', $this->listingPayload(['status' => 'unavailable']))
            ->assertSessionHasErrors('media');

        $this->assertDatabaseMissing('properties', ['title' => 'Fixes Suite House']);
    }

    // ---- 4. Listing window + renewal ----------------------------------------------

    public function test_show_and_index_expose_the_listing_window(): void
    {
        $owner = $this->newUser('Owner', 'Window Owner');
        $live = $this->makeProperty($owner, ['expires_at' => now()->addDays(20)]);
        $expired = $this->makeProperty($owner, ['status' => 'unavailable', 'expires_at' => now()->subDays(2)]);

        $this->actingAs($owner)->get('/owner/properties/'.$live->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.expired', false)
                ->where('listing.can_renew', true)
                ->where('listing.expires_at', $live->expires_at->toIso8601String()));

        $this->actingAs($owner)->get('/owner/properties/'.$expired->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.expired', true)
                ->where('listing.lapsed', false)
                ->where('listing.can_renew', true)
                ->where('listing.renew_until', $expired->expires_at->copy()->addDays(7)->toIso8601String()));

        $this->actingAs($owner)->get('/owner/properties')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('properties', 2)
                ->where('properties', fn ($rows) => collect($rows)->every(fn ($row) => isset($row['listing']['can_renew']))));
    }

    public function test_status_path_follows_the_renewal_grace_window(): void
    {
        $owner = $this->newUser('Owner', 'Grace Owner');
        $lapsed = $this->makeProperty($owner, ['status' => 'unavailable', 'expires_at' => now()->subDays(10)]);

        $this->actingAs($owner)
            ->put('/owner/properties/'.$lapsed->id.'/status', ['status' => 'available'])
            ->assertSessionHasErrors('status');
        $this->assertSame('unavailable', $lapsed->fresh()->status);

        $recent = $this->makeProperty($owner, ['status' => 'unavailable', 'expires_at' => now()->subDays(2)]);
        $this->actingAs($owner)
            ->put('/owner/properties/'.$recent->id.'/status', ['status' => 'available'])
            ->assertSessionHasNoErrors();
        $this->assertSame('available', $recent->fresh()->status);
        $this->assertTrue($recent->fresh()->expires_at->isAfter(now()->addDays(50)));
    }

    public function test_saving_a_lapsed_listing_lets_the_owner_publish_it_again(): void
    {
        $owner = $this->newUser('Owner', 'Review Owner');
        $lapsed = $this->makeProperty($owner, ['status' => 'unavailable', 'expires_at' => now()->subDays(10), 'cover_image' => '/uploads/x.jpg']);

        $this->actingAs($owner)->post('/owner/properties/'.$lapsed->id.'/renew')->assertSessionHasErrors('status');

        $this->actingAs($owner)
            ->put('/owner/properties/'.$lapsed->id, $this->listingPayload(['title' => 'Reviewed']))
            ->assertSessionHasNoErrors();
        $this->assertNull($lapsed->fresh()->expires_at);

        $this->actingAs($owner)
            ->put('/owner/properties/'.$lapsed->id.'/status', ['status' => 'available'])
            ->assertSessionHasNoErrors();
        $this->assertSame('available', $lapsed->fresh()->status);
    }

    // ---- 5. Applications count ------------------------------------------------------

    public function test_show_page_counts_applications(): void
    {
        $property = $this->makeProperty();
        $this->approvedApplication($property);
        RentalApplication::create(['property_id' => $property->id, 'applicant_id' => $this->newUser('Tenant')->id, 'status' => 'pending']);

        $this->actingAs($this->owner())->get('/owner/properties/'.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('property.applications_count', 2));
    }

    // ---- 6. Expiry reminders once per threshold ----------------------------------

    public function test_each_expiry_reminder_threshold_is_sent_once_per_window(): void
    {
        $owner = $this->newUser('Owner', 'Reminder Owner');
        $property = $this->makeProperty($owner, ['expires_at' => now()->addDays(14)->subMinute()]);
        $count = fn () => $owner->notifications()->where('type', ListingExpiryReminderNotification::class)->count();

        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame(1, $count());
        $this->assertSame(14, $property->fresh()->expiry_reminder_sent_days);

        $this->travel(1)->days();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame(1, $count());

        $this->travel(6)->days();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame(2, $count());

        $this->travel(6)->days();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame(3, $count());
        $this->assertSame(1, $property->fresh()->expiry_reminder_sent_days);
    }

    public function test_renewing_resets_the_reminder_tracker(): void
    {
        $owner = $this->newUser('Owner', 'Renew Reset Owner');
        $property = $this->makeProperty($owner, ['expires_at' => now()->addDays(5)]);

        app(ListingLifecycleService::class)->sendExpiryReminders([14, 7, 1]);
        $this->assertSame(7, $property->fresh()->expiry_reminder_sent_days);

        $this->actingAs($owner)->post('/owner/properties/'.$property->id.'/renew')->assertSessionHasNoErrors();
        $this->assertNull($property->fresh()->expiry_reminder_sent_days);
    }

    // ---- 7. Leasing a property more than once; dates + terms -------------------

    public function test_a_property_can_be_leased_again_after_its_lease_ended(): void
    {
        $property = $this->makeProperty();
        $this->lease($property, 'terminated');
        $application = $this->approvedApplication($property, $this->newUser('Tenant', 'Next Tenant'));

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertRedirect(route('owner.leases.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', Lease::where('application_id', $application->id)->value('status'));
    }

    public function test_a_property_with_an_open_lease_cannot_take_another(): void
    {
        $property = $this->makeProperty();
        $this->lease($property, 'draft');
        $application = $this->approvedApplication($property, $this->newUser('Tenant', 'Blocked Tenant'));

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertSessionHasErrors('application');

        $this->assertNull(Lease::where('application_id', $application->id)->first());
    }

    public function test_lease_on_a_property_that_is_not_available_explains_why(): void
    {
        $property = $this->makeProperty(null, ['status' => 'unavailable']);
        $application = $this->approvedApplication($property);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertSessionHasErrors(['application' => '"'.$property->title.'" is currently Unavailable. Set it to Available before generating a lease.']);
    }

    public function test_owner_chooses_lease_dates_and_the_listing_terms_are_carried_over(): void
    {
        $property = $this->makeProperty(null, ['payment_terms' => 'quarterly', 'currency' => 'ZWL', 'price' => 12000]);
        $application = $this->approvedApplication($property);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease', [
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(10)->addMonths(6)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $lease = Lease::where('application_id', $application->id)->firstOrFail();
        $this->assertSame(now()->addDays(10)->toDateString(), $lease->start_date->toDateString());
        $this->assertSame(now()->addDays(10)->addMonths(6)->toDateString(), $lease->end_date->toDateString());
        $this->assertSame('ZWL', $lease->currency);
        $this->assertSame('quarterly', $lease->payment_terms['frequency']);
        $this->assertSame(1, $lease->payment_terms['due_day']);
        $this->assertSame(12000.0, (float) $lease->rent_amount);
    }

    public function test_approving_a_new_applicant_after_the_previous_lease_ended(): void
    {
        $property = $this->makeProperty();
        $previous = $this->approvedApplication($property);
        $this->lease($property, 'terminated', null, ['application_id' => $previous->id]);
        $next = RentalApplication::create(['property_id' => $property->id, 'applicant_id' => $this->newUser('Tenant')->id, 'status' => 'pending']);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$next->id.'/approve')
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $next->fresh()->status);
    }

    public function test_owner_applications_page_exposes_lease_state_per_application(): void
    {
        $property = $this->makeProperty();
        $application = $this->approvedApplication($property);
        $lease = $this->lease($property, 'terminated', null, ['application_id' => $application->id]);

        $this->actingAs($this->owner())->get('/owner/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('properties', function ($properties) use ($property, $lease) {
                $row = collect($properties)->firstWhere('id', $property->id);

                return $row['open_leases_count'] === 0 && $row['applications'][0]['lease']['lease_no'] === $lease->lease_no;
            }));
    }

    public function test_lease_pages_expose_currency_and_payment_terms(): void
    {
        $property = $this->makeProperty(null, ['currency' => 'ZWL', 'payment_terms' => 'yearly']);
        $this->lease($property, 'sent', null, ['currency' => 'ZWL', 'payment_terms' => ['frequency' => 'yearly', 'due_day' => 1]]);

        $this->actingAs($this->owner())->get('/owner/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('leases', fn ($leases) => collect($leases)
                ->contains(fn ($lease) => $lease['currency'] === 'ZWL' && $lease['payment_terms']['frequency'] === 'yearly')));

        $this->actingAs($this->tenant())->get('/tenant/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('leases', fn ($leases) => collect($leases)
                ->contains(fn ($lease) => $lease['currency'] === 'ZWL' && $lease['property']['owner']['email'] === 'owner@dzimba.local')));
    }

    // ---- 8. Ending a lease ---------------------------------------------------------

    public function test_owner_ends_an_active_lease(): void
    {
        Notification::fake();
        $owner = $this->newUser('Owner', 'Ending Owner');
        $property = $this->makeProperty($owner, ['status' => 'occupied']);
        $lease = $this->lease($property, 'active');
        $renewal = $this->lease($property, 'draft', null, ['renewed_from_id' => $lease->id]);

        $this->actingAs($owner)
            ->post('/owner/leases/'.$lease->id.'/terminate', [
                'terminated_on' => now()->toDateString(),
                'reason' => 'Tenant relocated for work.',
            ])
            ->assertRedirect(route('owner.leases.index'))
            ->assertSessionHasNoErrors();

        $fresh = $lease->fresh();
        $this->assertSame('terminated', $fresh->status);
        $this->assertSame(now()->toDateString(), $fresh->terminated_on->toDateString());
        $this->assertSame('Tenant relocated for work.', $fresh->termination_reason);
        $this->assertSame('terminated', $renewal->fresh()->status);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_terminated']);
        $this->assertSame('available', $property->fresh()->status);
        $this->assertDatabaseHas('property_history', ['property_id' => $property->id, 'from_status' => 'occupied', 'to_status' => 'available']);

        Notification::assertSentTo($this->tenant(), LeaseTerminatedNotification::class);
        Notification::assertSentTo($owner, LeaseTerminatedNotification::class);
    }

    public function test_ending_a_lease_cancels_rent_invoices_for_periods_after_the_end_date(): void
    {
        $owner = $this->newUser('Owner', 'Invoice Owner');
        $property = $this->makeProperty($owner, ['status' => 'occupied']);
        $lease = $this->lease($property, 'active');
        app(RentService::class)->generateFor($lease);
        $endedOn = now()->startOfDay();

        $this->actingAs($owner)
            ->post('/owner/leases/'.$lease->id.'/terminate', ['terminated_on' => $endedOn->toDateString(), 'reason' => 'Moved out.'])
            ->assertRedirect(route('owner.leases.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('terminated', $lease->fresh()->status);

        $invoices = \App\Models\RentInvoice::where('lease_id', $lease->id)->get();
        $after = $invoices->filter(fn ($invoice) => $invoice->period_start->gt($endedOn));
        $before = $invoices->reject(fn ($invoice) => $invoice->period_start->gt($endedOn));

        $this->assertNotEmpty($after);
        $this->assertTrue($after->every(fn ($invoice) => $invoice->status === 'cancelled'), 'Future invoices must be cancelled.');
        $this->assertNotEmpty($before);
        $this->assertTrue($before->every(fn ($invoice) => $invoice->status !== 'cancelled'), 'Rent owed up to the end date stays owed.');
    }

    public function test_tenant_enquiries_page_carries_the_whole_thread(): void
    {
        $property = $this->makeProperty();
        $enquiry = Enquiry::create(['property_id' => $property->id, 'tenant_id' => $this->tenant()->id, 'message' => 'Is it available?', 'status' => 'new']);

        $this->actingAs($property->owner)->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Yes it is.']);
        $this->actingAs($this->tenant())->post(route('tenant.enquiries.reply', $enquiry->id), ['reply' => 'Great, can I view on Friday?'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->tenant())
            ->get(route('tenant.enquiries.index'))
            ->assertOk()
            ->assertInertia(function ($page) use ($enquiry) {
                $thread = collect($page->toArray()['props']['enquiries'])->firstWhere('id', $enquiry->id);
                $this->assertNotNull($thread);
                $this->assertSame(
                    [['owner', 'Yes it is.'], ['tenant', 'Great, can I view on Friday?']],
                    collect($thread['messages'])->map(fn ($m) => [$m['sender_role'], $m['body']])->all()
                );
            });
    }

    public function test_ending_a_lease_over_the_listing_limit_leaves_the_property_unavailable(): void
    {
        $owner = $this->newUser('Owner', 'Full Plan Owner');
        $this->makeProperty($owner);
        $property = $this->makeProperty($owner, ['status' => 'occupied']);
        $lease = $this->lease($property, 'active');
        $this->assertFalse(app(SubscriptionService::class)->hasQuota($owner, 1));

        $this->actingAs($owner)
            ->post('/owner/leases/'.$lease->id.'/terminate', ['terminated_on' => now()->toDateString(), 'reason' => 'Ended early.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('terminated', $lease->fresh()->status);
        $this->assertSame('unavailable', $property->fresh()->status);
    }

    public function test_ending_a_lease_is_validated_and_scoped(): void
    {
        $owner = $this->newUser('Owner', 'Validating Owner');
        $property = $this->makeProperty($owner, ['status' => 'occupied']);
        $active = $this->lease($property, 'active');
        $draft = $this->lease($this->makeProperty($owner), 'draft');

        $this->actingAs($owner)->post('/owner/leases/'.$active->id.'/terminate', ['terminated_on' => now()->addDay()->toDateString(), 'reason' => 'x'])
            ->assertSessionHasErrors('terminated_on');
        $this->actingAs($owner)->post('/owner/leases/'.$active->id.'/terminate', ['terminated_on' => now()->subYears(2)->toDateString(), 'reason' => 'x'])
            ->assertSessionHasErrors('terminated_on');
        $this->actingAs($owner)->post('/owner/leases/'.$active->id.'/terminate', ['terminated_on' => now()->toDateString()])
            ->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post('/owner/leases/'.$draft->id.'/terminate', ['terminated_on' => now()->toDateString(), 'reason' => 'x'])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->owner())->post('/owner/leases/'.$active->id.'/terminate', ['terminated_on' => now()->toDateString(), 'reason' => 'x'])
            ->assertNotFound();
        $this->actingAs($this->tenant())->post('/owner/leases/'.$active->id.'/terminate', ['terminated_on' => now()->toDateString(), 'reason' => 'x'])
            ->assertForbidden();
        auth()->logout();
        $this->post('/owner/leases/'.$active->id.'/terminate', [])->assertRedirect(route('login'));

        $this->assertSame('active', $active->fresh()->status);
    }

    // ---- 9. Delete guard -----------------------------------------------------------

    public function test_a_property_with_a_lease_cannot_be_deleted(): void
    {
        $property = $this->makeProperty();
        $this->lease($property, 'terminated');

        $this->actingAs($this->owner())
            ->delete('/owner/properties/'.$property->id)
            ->assertSessionHasErrors('property');

        $this->assertDatabaseHas('properties', ['id' => $property->id]);

        $this->actingAs($this->owner())->get('/owner/properties/'.$property->id)
            ->assertInertia(fn ($page) => $page->whereType('deleteBlockedReason', 'string'));
    }

    public function test_a_property_without_records_can_still_be_deleted(): void
    {
        $property = $this->makeProperty();

        $this->actingAs($this->owner())->delete('/owner/properties/'.$property->id)->assertRedirect(route('owner.properties.index'));

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    // ---- 10. Viewing slots ---------------------------------------------------------

    public function test_taken_and_past_slots_cannot_be_changed_or_deleted(): void
    {
        $property = $this->makeProperty();
        $taken = $this->slot($property, 3, 'taken');
        $past = ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->subDays(2)->setTime(10, 0),
            'ends_at' => now()->subDays(2)->setTime(11, 0),
            'status' => 'available',
        ]);
        $times = [
            'starts_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d\TH:i'),
        ];

        foreach ([$taken, $past] as $slot) {
            $this->actingAs($this->owner())->put('/owner/properties/'.$property->id.'/slots/'.$slot->id, $times)->assertStatus(409);
            $this->actingAs($this->owner())->delete('/owner/properties/'.$property->id.'/slots/'.$slot->id)->assertStatus(409);
            $this->assertDatabaseHas('viewing_slots', ['id' => $slot->id, 'starts_at' => $slot->starts_at]);
        }
    }

    public function test_requested_slot_cannot_move_and_slot_with_history_is_kept(): void
    {
        $property = $this->makeProperty();
        $requested = $this->slot($property, 3);
        $this->booking($requested);
        $history = $this->slot($property, 4);
        $this->booking($history, 'declined');
        $times = [
            'starts_at' => now()->addDays(6)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(6)->setTime(10, 0)->format('Y-m-d\TH:i'),
        ];

        $this->actingAs($this->owner())->put('/owner/properties/'.$property->id.'/slots/'.$requested->id, $times)->assertStatus(409);
        $this->actingAs($this->owner())->delete('/owner/properties/'.$property->id.'/slots/'.$history->id)->assertStatus(409);
        $this->actingAs($this->owner())->put('/owner/properties/'.$property->id.'/slots/'.$history->id, $times)->assertRedirect();

        $this->actingAs($this->owner())
            ->withHeader('X-Inertia', 'true')
            ->from('/owner/properties/'.$property->id.'/slots')
            ->delete('/owner/properties/'.$property->id.'/slots/'.$requested->id)
            ->assertRedirect('/owner/properties/'.$property->id.'/slots')
            ->assertSessionHas('error');
    }

    public function test_slot_list_flags_what_the_owner_can_change(): void
    {
        $property = $this->makeProperty();
        $open = $this->slot($property, 3);
        $taken = $this->slot($property, 4, 'taken');

        $this->actingAs($this->owner())->get('/owner/properties/'.$property->id.'/slots')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('slots', function ($slots) use ($open, $taken) {
                $rows = collect($slots)->keyBy('id');

                return $rows[$open->id]['is_editable'] === true
                    && $rows[$open->id]['is_deletable'] === true
                    && $rows[$taken->id]['is_editable'] === false
                    && $rows[$taken->id]['is_deletable'] === false;
            }));
    }

    public function test_viewing_requests_page_lists_properties_for_managing_times(): void
    {
        $property = $this->makeProperty();
        $this->slot($property, 3);
        $this->slot($property, 4, 'taken');

        $this->actingAs($this->owner())->get('/owner/viewings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('properties', fn ($rows) => collect($rows)->firstWhere('id', $property->id)['open_slots_count'] === 1));
    }

    // ---- 11. Viewing requests: rescheduled actions + notifications ---------------

    public function test_owner_can_decline_a_rescheduled_request_and_the_tenant_is_told(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $booking = $this->booking($this->slot($property), 'rescheduled');

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/decline')->assertRedirect();

        $this->assertSame('declined', $booking->fresh()->status);
        Notification::assertSentTo($this->tenant(), ViewingDeclinedNotification::class);
    }

    public function test_owner_can_cancel_requested_and_rescheduled_viewings_and_the_tenant_is_told(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $requested = $this->booking($this->slot($property, 3));
        $rescheduled = $this->booking($this->slot($property, 4), 'rescheduled');

        $this->actingAs($this->owner())->post('/owner/viewings/'.$requested->id.'/cancel')->assertRedirect();
        $this->actingAs($this->owner())->post('/owner/viewings/'.$rescheduled->id.'/cancel')->assertRedirect();

        $this->assertSame('cancelled', $requested->fresh()->status);
        $this->assertSame('cancelled', $rescheduled->fresh()->status);
        Notification::assertSentToTimes($this->tenant(), ViewingCancelledNotification::class, 2);
    }

    public function test_owner_is_told_when_the_tenant_cancels_or_confirms(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $toCancel = $this->booking($this->slot($property, 3), 'accepted');
        $toConfirm = $this->booking($this->slot($property, 4), 'rescheduled');

        $this->actingAs($this->tenant())->post('/tenant/viewings/'.$toCancel->id.'/cancel')->assertRedirect();
        $this->actingAs($this->tenant())->post('/tenant/viewings/'.$toConfirm->id.'/confirm')->assertRedirect();

        $this->assertSame('accepted', $toConfirm->fresh()->status);
        Notification::assertSentTo($this->owner(), ViewingCancelledNotification::class);
        Notification::assertSentTo($this->owner(), ViewingConfirmedNotification::class);
    }

    // ---- 12. Enquiry thread ----------------------------------------------------------

    public function test_owner_replies_are_appended_not_overwritten(): void
    {
        $enquiry = Enquiry::create(['property_id' => $this->makeProperty()->id, 'tenant_id' => $this->tenant()->id, 'message' => 'Is it free?', 'status' => 'new']);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'First answer'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Second answer'])->assertSessionHasNoErrors();

        $this->assertSame(['First answer', 'Second answer'], $enquiry->messages()->pluck('body')->all());
        $this->assertSame('Second answer', $enquiry->fresh()->reply);
        $this->assertSame('replied', $enquiry->fresh()->status);
    }

    public function test_tenant_can_reply_and_the_thread_returns_to_new_for_the_owner(): void
    {
        Notification::fake();
        $enquiry = Enquiry::create(['property_id' => $this->makeProperty()->id, 'tenant_id' => $this->tenant()->id, 'message' => 'Is it free?', 'status' => 'new']);
        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Yes it is'])->assertSessionHasNoErrors();

        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Great, can I view Friday?'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $enquiry->fresh();
        $this->assertSame('new', $fresh->status);
        $this->assertNull($fresh->read_at);
        $this->assertSame(['owner', 'tenant'], $enquiry->messages()->pluck('sender_role')->all());
        Notification::assertSentTo($this->owner(), EnquiryTenantRepliedNotification::class);

        $this->actingAs($this->owner())->get('/owner/enquiries/'.$enquiry->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('enquiry.messages', 2)
                ->where('enquiry.messages.1.body', 'Great, can I view Friday?')
                ->where('enquiry.property.currency', 'USD')
                ->where('enquiry.property.payment_terms', 'monthly'));
        $this->assertSame('read', $enquiry->fresh()->status);

        $this->actingAs($this->tenant())->get('/tenant/enquiries')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('enquiries', fn ($rows) => count(collect($rows)->firstWhere('id', $enquiry->id)['messages']) === 2));
    }

    public function test_tenant_reply_is_scoped_validated_and_closed_threads_are_locked(): void
    {
        $enquiry = Enquiry::create(['property_id' => $this->makeProperty()->id, 'tenant_id' => $this->tenant()->id, 'message' => 'Hi', 'status' => 'new']);

        $this->actingAs($this->newUser('Tenant', 'Nosy Tenant'))->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Mine?'])->assertNotFound();
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => ''])->assertSessionHasErrors('reply');
        $this->actingAs($this->owner())->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => 'x'])->assertForbidden();

        $enquiry->update(['status' => 'closed']);
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Hello?'])->assertSessionHasErrors('reply');
        $this->assertSame(0, $enquiry->messages()->count());

        auth()->logout();
        $this->post('/tenant/enquiries/'.$enquiry->id.'/reply', ['reply' => 'x'])->assertRedirect(route('login'));
    }

    // ---- 13. Interests props -----------------------------------------------------------

    public function test_interests_expose_the_real_badge_tier_and_the_status_filter_clears(): void
    {
        $property = $this->makeProperty();
        ExpressInterest::create(['property_id' => $property->id, 'tenant_id' => $this->newUser('Tenant', 'Unverified Tenant')->id, 'status' => 'interested']);

        $this->actingAs($this->owner())->get('/owner/interests?status=interested&property='.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'interested')
                ->where('queue.0.interests.0.tenant.badge_tier', 'none'));

        $this->actingAs($this->owner())->get('/owner/interests?status=&property='.$property->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.status', null));
    }

    // ---- 15. Dashboard income trend --------------------------------------------------

    public function test_owner_dashboard_ships_the_six_month_income_trend(): void
    {
        $this->actingAs($this->owner())->get('/owner')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('financial.income_trend', 6));
    }
}
