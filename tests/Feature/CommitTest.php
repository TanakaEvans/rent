<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Lease;
use App\Models\RentalApplication;
use App\Models\Role;
use App\Models\User;
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

    public function test_guests_are_redirected_to_login_for_lease_routes(): void
    {
        $this->get('/tenant/leases')->assertRedirect(route('login'));
        $this->get('/owner/leases')->assertRedirect(route('login'));
        $this->post('/owner/applications/1/lease', [])->assertRedirect(route('login'));
    }
}