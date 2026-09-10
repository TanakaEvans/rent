<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Lease;
use App\Models\RentalApplication;
use App\Models\Role;
use App\Models\User;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class CommitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Lease::query()->delete();
        RentalApplication::query()->delete();
        DatabaseNotification::query()->delete();
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

    private function makeProperty(?User $owner = null, string $status = 'available'): Property
    {
        return Property::create([
            'owner_id' => ($owner ?? $this->owner())->id,
            'title' => 'Test Home '.mt_rand(1000, 9999),
            'description' => 'A listing created for the test suite.',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'deposit' => 850.00,
            'furnished' => false,
            'status' => $status,
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
            'address' => '1 Test Road',
            'amenities' => ['parking'],
            'featured' => false,
            'verified' => true,
            'available_from' => now()->addDays(5),
        ]);
    }

    private function makeLease(Property $property, ?User $tenant = null, string $status = 'draft'): Lease
    {
        return Lease::create([
            'property_id' => $property->id,
            'tenant_id' => ($tenant ?? $this->tenant())->id,
            'lease_no' => 'LSE-2026-'.mt_rand(7000, 8999),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 850,
            'deposit_amount' => 850,
            'payment_terms' => ['frequency' => 'monthly'],
            'status' => $status,
            'clause_version' => 1,
        ]);
    }

    public function test_tenant_can_apply_to_an_available_property(): void
    {
        $property = $this->makeProperty();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'I am interested in a long-term lease.'])
            ->assertRedirect();

        $this->assertDatabaseHas('rental_applications', [
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'status' => 'pending',
        ]);
        $this->assertSame('available', $property->fresh()->status);
    }

    public function test_application_message_is_limited_to_1000_characters(): void
    {
        $property = $this->makeProperty();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseMissing('rental_applications', ['property_id' => $property->id]);
    }

    public function test_tenant_cannot_hold_two_active_applications_for_one_property(): void
    {
        $property = $this->makeProperty();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'First'])
            ->assertRedirect();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'Second'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1, RentalApplication::where('property_id', $property->id)->count());
    }

    public function test_tenant_can_reapply_after_a_rejection(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'First try',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/reject', ['reason' => 'Lease already finalised'])
            ->assertRedirect();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'Second try'])
            ->assertRedirect();

        $this->assertSame(2, RentalApplication::where('property_id', $property->id)->count());
    }

    public function test_tenant_cannot_apply_to_an_unavailable_property(): void
    {
        $property = $this->makeProperty(null, 'occupied');

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'Hello'])
            ->assertNotFound();
    }

    public function test_owner_is_notified_when_tenant_applies(): void
    {
        Notification::fake();
        $property = $this->makeProperty();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'Please consider me.'])
            ->assertRedirect();

        Notification::assertSentTo(
            $this->owner(),
            \App\Notifications\NewApplicationNotification::class,
        );
    }

    public function test_tenant_applications_page_lists_only_own(): void
    {
        $property = $this->makeProperty(null, 'available');
        $secondProperty = $this->makeProperty(null, 'available');

        RentalApplication::create([
            'property_id' => $secondProperty->id,
            'applicant_id' => $this->newTenant()->id,
            'message' => 'Other tenant',
            'status' => 'pending',
        ]);
        RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Mine',
            'status' => 'pending',
        ]);

        $this->actingAs($this->tenant())->get('/tenant/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Applications')
                ->has('applications', 1)
                ->where('applications.0.message', 'Mine'));
    }

    public function test_owner_applications_page_is_grouped_and_scoped_to_own_properties(): void
    {
        $ownProperty = $this->makeProperty();
        $other = $this->newOwner();
        $otherProperty = $this->makeProperty($other);

        RentalApplication::create([
            'property_id' => $ownProperty->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'On my listing',
            'status' => 'pending',
        ]);
        RentalApplication::create([
            'property_id' => $otherProperty->id,
            'applicant_id' => $this->newTenant()->id,
            'message' => 'On theirs',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())->get('/owner/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Applications/Index')
                ->has('properties', 1)
                ->where('properties.0.id', $ownProperty->id));

        $this->actingAs($other)->get('/owner/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Applications/Index')
                ->where('properties.0.id', $otherProperty->id));
    }

    public function test_owner_approves_an_application_without_moving_the_property(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Consider me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/approve')
            ->assertRedirect();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame($this->owner()->id, $application->fresh()->reviewed_by);
        $this->assertSame('available', $property->fresh()->status);

        Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\ApplicationApprovedNotification::class,
        );
    }

    public function test_owner_cannot_act_on_another_owners_application(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Consider me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->newOwner())
            ->post('/owner/applications/'.$application->id.'/approve')
            ->assertNotFound();

        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_only_one_application_can_be_approved_per_property(): void
    {
        $property = $this->makeProperty();
        $first = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'First',
            'status' => 'pending',
        ]);
        $second = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->newTenant()->id,
            'message' => 'Second',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())->post('/owner/applications/'.$first->id.'/approve')->assertRedirect();
        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$second->id.'/approve')
            ->assertSessionHasErrors('status');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_terminal_applications_are_immutable(): void
    {
        $property = $this->makeProperty();
        $approveTarget = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Approve me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())->post('/owner/applications/'.$approveTarget->id.'/approve')->assertRedirect();

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$approveTarget->id.'/reject', ['reason' => 'Changed my mind'])
            ->assertSessionHasErrors('status');
        $this->assertSame('approved', $approveTarget->fresh()->status);

        $rejectTarget = RentalApplication::create([
            'property_id' => $this->makeProperty()->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Reject me',
            'status' => 'pending',
        ]);
        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$rejectTarget->id.'/reject', ['reason' => 'Not a fit'])
            ->assertRedirect();
        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$rejectTarget->id.'/shortlist')
            ->assertSessionHasErrors('status');
        $this->assertSame('rejected', $rejectTarget->fresh()->status);
    }

    public function test_shortlist_toggles_between_pending_and_shortlisted(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Shortlist me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())->post('/owner/applications/'.$application->id.'/shortlist')->assertRedirect();
        $this->assertSame('shortlisted', $application->fresh()->status);

        $this->actingAs($this->owner())->post('/owner/applications/'.$application->id.'/shortlist')->assertRedirect();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Reject me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/reject', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_reject_records_reason_and_notifies_tenant(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Reject me',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/reject', ['reason' => 'Position already offered to another applicant'])
            ->assertRedirect();

        $fresh = $application->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Position already offered to another applicant', $fresh->reject_reason);
        $this->assertSame($this->owner()->id, $fresh->reviewed_by);

        Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\ApplicationRejectedNotification::class,
        );
    }

    public function test_guests_are_redirected_to_login_for_application_routes(): void
    {
        $this->get('/tenant/applications')->assertRedirect(route('login'));
        $this->get('/owner/applications')->assertRedirect(route('login'));
        $this->post('/tenant/applications/1', [])->assertRedirect(route('login'));
    }

    public function test_pipeline_apply_approve_lease_reserves_property(): void
    {
        Notification::fake();
        $property = $this->makeProperty();

        $this->actingAs($this->tenant())
            ->post('/tenant/applications/'.$property->id, ['message' => 'I am the applicant'])
            ->assertRedirect();

        $application = RentalApplication::where('property_id', $property->id)->first();

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/approve')
            ->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertRedirect(route('owner.leases.index'));

        $lease = Lease::where('application_id', $application->id)->first();
        $this->assertNotNull($lease);
        $this->assertStringStartsWith('LSE-'.now()->year.'-', $lease->lease_no);
        $this->assertSame($property->id, $lease->property_id);
        $this->assertSame($this->tenant()->id, $lease->tenant_id);
        $this->assertSame('draft', $lease->status);
        $this->assertSame(850.00, (float) $lease->rent_amount);
        $this->assertSame(850.00, (float) $lease->deposit_amount);
        $this->assertSame('reserved', $property->fresh()->status);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_created']);

        Notification::assertSentTo($this->tenant(), \App\Notifications\LeaseCreatedNotification::class);
    }

    public function test_lease_cannot_be_generated_from_a_non_approved_application(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Still pending',
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('leases', 0);
        $this->assertSame('available', $property->fresh()->status);
    }

    public function test_lease_cannot_be_generated_twice_for_the_same_application(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Approve me',
            'status' => 'approved',
            'reviewed_by' => $this->owner()->id,
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertRedirect(route('owner.leases.index'));

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertSessionHasErrors('application');

        $this->assertDatabaseCount('leases', 1);
    }

    public function test_owner_cannot_generate_a_lease_for_another_owners_application(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Approve me',
            'status' => 'approved',
            'reviewed_by' => $this->owner()->id,
        ]);

        $this->actingAs($this->newOwner())
            ->post('/owner/applications/'.$application->id.'/lease')
            ->assertNotFound();

        $this->assertDatabaseCount('leases', 0);
    }

    public function test_generating_a_lease_auto_rejects_remaining_active_applicants(): void
    {
        Notification::fake();
        $property = $this->makeProperty();
        $chosen = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Pick me',
            'status' => 'approved',
            'reviewed_by' => $this->owner()->id,
        ]);
        $firstOther = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->newTenant()->id,
            'message' => 'Pending rival',
            'status' => 'pending',
        ]);
        $secondOther = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->newTenant()->id,
            'message' => 'Shortlisted rival',
            'status' => 'shortlisted',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$chosen->id.'/lease')
            ->assertRedirect(route('owner.leases.index'));

        $this->assertSame('rejected', $firstOther->fresh()->status);
        $this->assertSame('rejected', $secondOther->fresh()->status);
        $this->assertSame('A lease has been created for another applicant.', $firstOther->fresh()->reject_reason);
        $this->assertSame('reserved', $property->fresh()->status);

        Notification::assertSentTo(
            $firstOther->applicant,
            \App\Notifications\ApplicationRejectedNotification::class
        );
        Notification::assertSentTo(
            $secondOther->applicant,
            \App\Notifications\ApplicationRejectedNotification::class
        );
    }

    public function test_lease_end_date_must_fall_after_start_date(): void
    {
        $property = $this->makeProperty();
        $application = RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $this->tenant()->id,
            'message' => 'Approve me',
            'status' => 'approved',
            'reviewed_by' => $this->owner()->id,
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/applications/'.$application->id.'/lease', [
                'start_date' => '2026-12-01',
                'end_date' => '2026-11-30',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('leases', 0);
    }

    public function test_owner_leases_page_is_scoped_to_own_properties(): void
    {
        $ownProperty = $this->makeProperty();
        Lease::create([
            'property_id' => $ownProperty->id,
            'tenant_id' => $this->tenant()->id,
            'lease_no' => 'LSE-2026-9001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 850,
            'deposit_amount' => 850,
            'payment_terms' => ['frequency' => 'monthly'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);
        $other = $this->newOwner();
        $otherProperty = $this->makeProperty($other);
        Lease::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->newTenant()->id,
            'lease_no' => 'LSE-2026-9002',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 620,
            'deposit_amount' => 620,
            'payment_terms' => ['frequency' => 'monthly'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);

        $this->actingAs($this->owner())->get('/owner/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Leases/Index')
                ->has('leases', 1)
                ->where('leases.0.lease_no', 'LSE-2026-9001'));

        $this->actingAs($other)->get('/owner/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Leases/Index')
                ->has('leases', 1)
                ->where('leases.0.lease_no', 'LSE-2026-9002'));
    }

    public function test_tenant_leases_page_lists_only_own(): void
    {
        $property = $this->makeProperty();
        Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'lease_no' => 'LSE-2026-9003',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 450,
            'deposit_amount' => 450,
            'payment_terms' => ['frequency' => 'monthly'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);
        $secondProperty = $this->makeProperty();
        Lease::create([
            'property_id' => $secondProperty->id,
            'tenant_id' => $this->newTenant()->id,
            'lease_no' => 'LSE-2026-9004',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 300,
            'deposit_amount' => 300,
            'payment_terms' => ['frequency' => 'monthly'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);

        $this->actingAs($this->tenant())->get('/tenant/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Leases')
                ->has('leases', 1)
                ->where('leases.0.lease_no', 'LSE-2026-9003'));
    }

    public function test_owner_sends_draft_lease_for_signature(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->makeLease($property);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/send', [])
            ->assertRedirect();

        $this->assertSame('sent', $lease->fresh()->status);
        $this->assertSame('reserved', $property->fresh()->status);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_sent']);

        Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\LeaseSentForSignatureNotification::class
        );
    }

    public function test_lease_can_only_be_sent_from_draft(): void
    {
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->makeLease($property);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/send', [])
            ->assertRedirect();
        $this->assertSame('sent', $lease->fresh()->status);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/send', [])
            ->assertSessionHasErrors('status');
    }

    public function test_owner_cannot_send_another_owners_lease(): void
    {
        $property = $this->makeProperty($this->newOwner());
        $lease = $this->makeLease($property, $this->newTenant());

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/send', [])
            ->assertNotFound();

        $this->assertSame('draft', $lease->fresh()->status);
    }

    public function test_single_signature_keeps_the_lease_sent(): void
    {
        $property = $this->makeProperty();
        $lease = $this->makeLease($property, null, 'sent');

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', ['signature' => 'T. Gwese'])
            ->assertRedirect();

        $signature = $lease->signatures()->first();
        $this->assertNotNull($signature);
        $this->assertSame($this->tenant()->id, $signature->user_id);
        $this->assertSame('T. Gwese', $signature->signature_payload);
        $this->assertSame('sent', $lease->fresh()->status);
        $this->assertSame('available', $property->fresh()->status);

        $this->actingAs($this->tenant())->get('/tenant/leases')
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Leases')
                ->has('leases.0.signatures', 1)
                ->where('leases.0.signatures.0.user_id', $this->tenant()->id));
    }

    public function test_signature_payload_defaults_to_the_signers_name(): void
    {
        $property = $this->makeProperty();
        $lease = $this->makeLease($property, null, 'sent');

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', [])
            ->assertRedirect();

        $this->assertSame(
            $this->tenant()->name,
            $lease->signatures()->first()->signature_payload
        );
    }

    public function test_tenant_cannot_sign_a_lease_they_do_not_hold(): void
    {
        $property = $this->makeProperty();
        $lease = $this->makeLease($property, $this->newTenant(), 'sent');

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', [])
            ->assertNotFound();

        $this->assertDatabaseCount('lease_signatures', 0);
    }

    public function test_owner_cannot_sign_a_lease_on_another_owners_property(): void
    {
        $property = $this->makeProperty($this->newOwner());
        $lease = $this->makeLease($property, $this->newTenant(), 'sent');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/sign', [])
            ->assertNotFound();

        $this->assertDatabaseCount('lease_signatures', 0);
    }

    public function test_lease_cannot_be_signed_before_it_is_sent(): void
    {
        $property = $this->makeProperty();
        $lease = $this->makeLease($property);

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', [])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('lease_signatures', 0);
    }

    public function test_a_party_cannot_sign_twice(): void
    {
        $property = $this->makeProperty();
        $lease = $this->makeLease($property, null, 'sent');

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', [])
            ->assertRedirect();

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', [])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('lease_signatures', 1);
    }

    public function test_both_signatures_activate_the_lease_and_occupy_the_property(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->makeLease($property, null, 'sent');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/sign', ['signature' => 'O. Dzimba'])
            ->assertRedirect();
        $this->assertSame('sent', $lease->fresh()->status);

        $this->actingAs($this->tenant())
            ->post('/tenant/leases/'.$lease->id.'/sign', ['signature' => 'T. Gwese'])
            ->assertRedirect();

        $fresh = $lease->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertSame('occupied', $property->fresh()->status);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_signed_final']);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_activated']);
        $this->assertDatabaseCount('lease_signatures', 2);

        Notification::assertSentTo(
            $this->owner(),
            \App\Notifications\LeaseSignedNotification::class
        );
    }

    public function test_guests_are_redirected_to_login_for_signature_routes(): void
    {
        $this->post('/owner/leases/1/send', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/sign', [])->assertRedirect(route('login'));
        $this->post('/tenant/leases/1/sign', [])->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_for_lease_routes(): void
    {
        $this->get('/tenant/leases')->assertRedirect(route('login'));
        $this->get('/owner/leases')->assertRedirect(route('login'));
        $this->post('/owner/applications/1/lease', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/renew', [])->assertRedirect(route('login'));
    }

    public function test_owner_renews_an_active_lease_copying_the_terms(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'active');
        $lease->update([
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertRedirect(route('owner.leases.index'));

        $renewal = Lease::where('renewed_from_id', $lease->id)->first();
        $this->assertNotNull($renewal);
        $this->assertSame($property->id, $renewal->property_id);
        $this->assertSame($this->tenant()->id, $renewal->tenant_id);
        $this->assertNull($renewal->application_id);
        $this->assertSame((float) $lease->rent_amount, (float) $renewal->rent_amount);
        $this->assertSame((float) $lease->deposit_amount, (float) $renewal->deposit_amount);
        $this->assertSame($lease->payment_terms, $renewal->payment_terms);
        $this->assertSame($lease->clause_version, $renewal->clause_version);
        $this->assertSame('draft', $renewal->status);
        $this->assertSame($lease->end_date->toDateString(), $renewal->start_date->toDateString());
        $this->assertSame($lease->end_date->copy()->addYear()->toDateString(), $renewal->end_date->toDateString());
        $this->assertSame('active', $lease->fresh()->status);
        $this->assertSame('occupied', $property->fresh()->status);
        $this->assertStringStartsWith('LSE-'.now()->year.'-', $renewal->lease_no);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $renewal->id, 'action' => 'lease_renewal']);
    }

    public function test_renewal_accepts_updated_dates_and_validates_them(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'active');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [
                'start_date' => '2026-12-01',
                'end_date' => '2027-11-30',
            ])
            ->assertRedirect(route('owner.leases.index'));

        $renewal = Lease::where('renewed_from_id', $lease->id)->first();
        $this->assertNotNull($renewal);
        $this->assertSame('2026-12-01', $renewal->start_date->toDateString());
        $this->assertSame('2027-11-30', $renewal->end_date->toDateString());

        $secondLease = $this->makeLease($property, null, 'active');
        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$secondLease->id.'/renew', [
                'start_date' => '2026-12-01',
                'end_date' => '2026-11-30',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('leases', 3);
    }

    public function test_only_an_active_lease_can_be_renewed(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $draft = $this->makeLease($property);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$draft->id.'/renew', [])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('leases', 1);
    }

    public function test_owner_cannot_renew_another_owners_lease(): void
    {
        $property = $this->makeProperty($this->newOwner());
        $lease = $this->makeLease($property, $this->newTenant(), 'active');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertNotFound();

        $this->assertDatabaseCount('leases', 1);
    }

    public function test_only_one_renewal_can_be_in_flight_at_a_time(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'active');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertRedirect();
        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('leases', 2);
    }

    public function test_signing_a_renewal_activates_it_and_marks_the_original_renewed(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'active');
        $lease->update(['end_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertRedirect();

        $renewal = Lease::where('renewed_from_id', $lease->id)->first();
        $this->assertSame('draft', $renewal->status);

        $this->actingAs($this->owner())->post('/owner/leases/'.$renewal->id.'/send', [])->assertRedirect();
        $this->actingAs($this->owner())->post('/owner/leases/'.$renewal->id.'/sign', [])->assertRedirect();
        $this->actingAs($this->tenant())->post('/tenant/leases/'.$renewal->id.'/sign', [])->assertRedirect();

        $this->assertSame('active', $renewal->fresh()->status);
        $this->assertSame('renewed', $lease->fresh()->status);
        $this->assertSame('occupied', $property->fresh()->status);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'action' => 'lease_renewed']);
        $this->assertDatabaseHas('lease_history', ['lease_id' => $lease->id, 'details->superseded_by' => $renewal->fresh()->lease_no]);
    }

    public function test_a_renewed_lease_cannot_be_renewed_again(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'renewed');

        $this->actingAs($this->owner())
            ->post('/owner/leases/'.$lease->id.'/renew', [])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('leases', 1);
    }

    public function test_tenant_lease_page_exposes_renewal_linkage(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $original = $this->makeLease($property, null, 'active');
        $original->update(['lease_no' => 'LSE-2026-9900']);
        $renewal = $this->makeLease($property, null, 'draft');
        $renewal->update(['lease_no' => 'LSE-2026-9901', 'renewed_from_id' => $original->id]);

        $response = $this->actingAs($this->tenant())->get('/tenant/leases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Leases')->has('leases', 2));

        $leases = $response->inertiaProps('leases');
        $renewalEntry = collect($leases)->first(fn ($entry) => $entry['lease_no'] === 'LSE-2026-9901');
        $this->assertSame('LSE-2026-9900', $renewalEntry['renewed_from']['lease_no']);
    }

    private function activateLease(Property $property, ?User $tenant = null, ?string $leaseNo = null, ?User $signAsOwner = null, ?string $ownerPayload = 'O. Dzimba'): Lease
    {
        $lease = $this->makeLease($property, $tenant, 'sent');
        if ($leaseNo) {
            $lease->update(['lease_no' => $leaseNo]);
        }

        $this->actingAs($signAsOwner ?? $this->owner())->post('/owner/leases/'.$lease->id.'/sign', ['signature' => $ownerPayload])->assertRedirect();
        $this->actingAs(($tenant ?? $this->tenant()))->post('/tenant/leases/'.$lease->id.'/sign', ['signature' => 'T. Gwese'])->assertRedirect();

        return $lease->fresh();
    }

    public function test_both_signatures_store_an_agreement_document(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property, null, 'LSE-2026-8101');

        $document = Document::where('lease_id', $lease->id)->latest('version')->first();
        $this->assertNotNull($document);
        $this->assertSame('lease_agreement', $document->type);
        $this->assertSame('text/plain', $document->mime);
        $this->assertSame(1, $document->version);
        $this->assertSame('Lease Agreement LSE-2026-8101', $document->name);
        $this->assertStringContainsString('DZIMBA LEASE AGREEMENT', $document->content);
        $this->assertStringContainsString('LSE-2026-8101', $document->content);
        $this->assertStringContainsString('O. Dzimba', $document->content);
        $this->assertStringContainsString('T. Gwese', $document->content);
        $this->assertSame(strlen($document->content), $document->size);
    }

    public function test_signing_a_renewal_stores_its_own_agreement(): void
    {
        $property = $this->makeProperty(null, 'occupied');
        $lease = $this->makeLease($property, null, 'active');
        $lease->update(['end_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($this->owner())->post('/owner/leases/'.$lease->id.'/renew', [])->assertRedirect();
        $renewal = Lease::where('renewed_from_id', $lease->id)->first();
        $this->assertSame('draft', $renewal->status);

        $this->actingAs($this->owner())->post('/owner/leases/'.$renewal->id.'/send', [])->assertRedirect();
        $this->actingAs($this->owner())->post('/owner/leases/'.$renewal->id.'/sign', [])->assertRedirect();
        $this->actingAs($this->tenant())->post('/tenant/leases/'.$renewal->id.'/sign', [])->assertRedirect();

        $document = Document::where('lease_id', $renewal->id)->latest('version')->first();
        $this->assertNotNull($document);
        $this->assertSame(1, $document->version);
        $this->assertStringContainsString($renewal->fresh()->lease_no, $document->content);
        $this->assertStringContainsString('ACTIVE', strtoupper($document->content));
    }

    public function test_owner_and_tenant_can_view_their_agreement(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property);
        $document = $lease->document;

        $this->actingAs($this->owner())
            ->get('/documents/'.$document->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Documents/Show')->where('document.content', $document->content));

        $this->actingAs($this->tenant())
            ->get('/documents/'.$document->id)
            ->assertOk();
    }

    public function test_tenant_can_download_their_agreement(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property);
        $document = $lease->document;

        $this->actingAs($this->tenant())
            ->get('/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.strtolower($lease->lease_no).'-agreement-v1.txt"')
            ->assertSeeText('DZIMBA LEASE AGREEMENT');
    }

    public function test_other_tenants_and_owners_get_a_404_on_a_document(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property);
        $document = $lease->document;

        $this->actingAs($this->newTenant())->get('/documents/'.$document->id)->assertNotFound();
        $this->actingAs($this->newOwner())->get('/documents/'.$document->id)->assertNotFound();
        $this->actingAs($this->newOwner())->get('/documents/'.$document->id.'/download')->assertNotFound();
    }

    public function test_admin_can_view_any_document(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property);
        $document = $lease->document;

        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/documents/'.$document->id)->assertOk();
        $this->actingAs($admin)->get('/documents/'.$document->id.'/download')->assertOk();
    }

    public function test_owner_document_index_is_scoped_to_their_properties(): void
    {
        Notification::fake();
        $secondOwner = $this->newOwner();
        $secondTenant = $this->newTenant();
        $this->activateLease($this->makeProperty(null, 'reserved'));
        $theirs = $this->activateLease($this->makeProperty($secondOwner, 'reserved'), $secondTenant, null, $secondOwner, 'O. Second');

        $this->actingAs($this->owner())
            ->get('/owner/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Documents/Index')->has('documents', 1));

        $this->actingAs($secondOwner)
            ->get('/owner/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Documents/Index')->where('documents.0.lease.lease_no', $theirs->lease_no));
    }

    public function test_tenant_document_index_is_scoped_to_their_leases(): void
    {
        Notification::fake();
        $this->activateLease($this->makeProperty(null, 'reserved'));
        $this->activateLease($this->makeProperty(null, 'reserved'), null);

        $this->actingAs($this->tenant())
            ->get('/tenant/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Documents')->has('documents', 2));

        $this->actingAs($this->newTenant())
            ->get('/tenant/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Documents')->has('documents', 0));
    }

    public function test_re_storing_an_agreement_bumps_the_version(): void
    {
        Notification::fake();
        $property = $this->makeProperty(null, 'reserved');
        $lease = $this->activateLease($property);

        app(DocumentService::class)->storeLeaseAgreement($lease);
        app(DocumentService::class)->storeLeaseAgreement($lease);

        $document = Document::where('lease_id', $lease->id)->latest('version')->first();
        $this->assertSame(3, $document->version);
        $this->assertDatabaseCount('documents', 1);
        $this->assertSame(strlen($document->content), $document->size);
    }

    public function test_guests_are_redirected_to_login_for_document_routes(): void
    {
        $this->get('/owner/documents')->assertRedirect(route('login'));
        $this->get('/tenant/documents')->assertRedirect(route('login'));
        $this->get('/documents/1')->assertRedirect(route('login'));
        $this->get('/documents/1/download')->assertRedirect(route('login'));
    }
}