<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRating;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Notifications\ContractorRatedNotification;
use App\Notifications\EmergencyMaintenanceNotification;
use App\Notifications\MaintenanceAssignedNotification;
use App\Notifications\MaintenanceClosedNotification;
use App\Notifications\MaintenanceSlaBreachedNotification;
use App\Notifications\MaintenanceStartedNotification;
use App\Notifications\MaintenanceTenantConfirmedNotification;
use App\Notifications\MaintenanceWorkCompletedNotification;
use App\Notifications\NewMaintenanceRequestNotification;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function service(): MaintenanceService
    {
        return app(MaintenanceService::class);
    }

    private function owner(): User
    {
        return User::where('username', 'owner')->firstOrFail();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('username', 'staff')->firstOrFail();
    }

    /**
     * The demo occupancy: the Kumalo home with an active tenant lease.
     */
    private function rentedHome(): Property
    {
        return Property::where('title', '4 Bedroom Family Home in Kumalo')->firstOrFail();
    }

    private function strangerTenant(): User
    {
        $user = User::factory()->create(['name' => 'Stranger Tenant', 'email' => 'maintenance-stranger@example.test', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Tenant')->firstOrFail()->id);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'property_id' => $this->rentedHome()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Kitchen tap leaking',
            'description' => 'The tap drips water all day.',
        ], $overrides);
    }

    private function updateConfig(string $key, string $value): void
    {
        SystemConfiguration::where('key', $key)->update(['value' => $value]);
        Cache::forget('dzimba.config.'.$key);
    }

    // ---------- report (FR-01) ----------

    public function test_tenant_report_creates_request_with_unique_mr_number_and_sla(): void
    {
        $tenant = $this->tenant();

        $this->actingAs($tenant)->post('/tenant/maintenance', $this->payload())->assertRedirect();

        $request = MaintenanceRequest::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^MR-\d{4}-\d{5}$/', $request->request_no);
        $this->assertSame('reported', $request->status);
        $this->assertSame('plumbing', $request->category);
        $this->assertSame('medium', $request->priority);
        $this->assertSame($this->rentedHome()->id, $request->property_id);

        $this->assertTrue(
            $request->sla_due_at->between(now()->addHours(95), now()->addHours(97)),
            'medium SLA should default to 96 hours.'
        );
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'reported', 'actor_id' => $tenant->id]);
    }

    public function test_emergency_report_notifies_owner_and_staff_immediately(): void
    {
        Notification::fake();

        $this->actingAs($this->tenant())
            ->post('/tenant/maintenance', $this->payload([
                'category' => 'safety',
                'priority' => 'emergency',
                'title' => 'Sparks from the geyser',
            ]))
            ->assertRedirect();

        $request = MaintenanceRequest::latest('id')->firstOrFail();

        Notification::assertSentTo($this->owner(), NewMaintenanceRequestNotification::class);
        Notification::assertSentTo($this->admin(), EmergencyMaintenanceNotification::class);
        Notification::assertSentTo($this->staff(), EmergencyMaintenanceNotification::class);

        $this->assertNotNull($request->sla_due_at);
        $this->assertTrue($request->sla_due_at->between(now()->addHours(23)->addMinutes(59), now()->addHours(24)->addMinutes(1)));
    }

    public function test_only_tenant_with_active_lease_can_report(): void
    {
        $stranger = $this->strangerTenant();

        $this->actingAs($stranger)->post('/tenant/maintenance', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('maintenance_requests', 2);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->actingAs($this->tenant())
            ->from('/tenant/maintenance')
            ->post('/tenant/maintenance', $this->payload(['category' => 'roofing']))
            ->assertRedirect('/tenant/maintenance')
            ->assertSessionHasErrors('category');
    }

    public function test_unknown_priority_is_rejected(): void
    {
        $this->actingAs($this->tenant())
            ->from('/tenant/maintenance')
            ->post('/tenant/maintenance', $this->payload(['priority' => 'urgent']))
            ->assertRedirect('/tenant/maintenance')
            ->assertSessionHasErrors('priority');
    }

    public function test_sla_hours_are_config_driven(): void
    {
        $this->updateConfig('maintenance.sla.high_hours', '6');
        $this->actingAs($this->tenant())
            ->post('/tenant/maintenance', $this->payload(['priority' => 'high']))
            ->assertRedirect();

        $request = MaintenanceRequest::latest('id')->firstOrFail();
        $this->assertTrue(
            $request->sla_due_at->between(now()->addHours(5)->addMinutes(59), now()->addHours(6)->addMinutes(1)),
            'SLA should track the configured high-priority window.'
        );
    }

    // ---------- triage & escalation (FR-07, NFR-01) ----------

    public function test_escalate_command_breaches_past_sla_requests_once_and_pages_staff(): void
    {
        $request = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $this->rentedHome()->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'electrical',
            'priority' => 'high',
            'title' => 'Flickering lights',
            'description' => 'The lounge lights keep flickering.',
            'status' => 'reported',
            'sla_due_at' => now()->subDay(),
        ]);

        Notification::fake();

        $this->artisan('maintenance:escalate')->assertSuccessful();

        $this->assertNotNull($request->fresh()->escalated_at);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'escalated', 'actor_id' => null]);
        Notification::assertSentTo($this->admin(), MaintenanceSlaBreachedNotification::class);
        Notification::assertSentTo($this->staff(), MaintenanceSlaBreachedNotification::class);

        // Idempotent: a second sweep must not re-alert.
        $this->artisan('maintenance:escalate')->assertSuccessful();
        Notification::assertSentTo($this->admin(), MaintenanceSlaBreachedNotification::class, 1);
    }

    public function test_non_reported_requests_are_not_escalated(): void
    {
        MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $this->rentedHome()->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'electrical',
            'priority' => 'high',
            'title' => 'Flickering lights',
            'description' => 'The lounge lights keep flickering.',
            'status' => 'assigned',
            'sla_due_at' => now()->subDay(),
        ]);

        $this->assertSame(0, $this->service()->escalateDue());
    }

    public function test_escalation_respects_the_master_switch(): void
    {
        MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $this->rentedHome()->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Slow drain',
            'description' => 'The bathroom drain is blocked.',
            'status' => 'reported',
            'sla_due_at' => now()->subDay(),
        ]);

        $this->updateConfig('maintenance.escalation_enabled', '0');

        $this->assertSame(0, $this->service()->escalateDue());
    }

    // ---------- staff escalation queue (AC-03) ----------

    public function test_admin_queue_lists_sla_breaches_and_ack_clears_it(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->assertNotNull($request->escalated_at);

        $this->actingAs($this->admin())
            ->get('/admin/maintenance/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Maintenance/Escalations'));

        $this->actingAs($this->admin())
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertRedirect();

        $this->assertNull($request->fresh()->escalated_at);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'acknowledged', 'actor_id' => $this->admin()->id]);
    }

    public function test_ack_requires_an_active_escalation(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $this->assertNull($request->escalated_at);

        $this->actingAs($this->admin())
            ->from('/admin/maintenance/escalations')
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertRedirect('/admin/maintenance/escalations')
            ->assertSessionHasErrors('request');
    }

    // ---------- owner scope (row-level) ----------

    public function test_owner_queue_only_shows_their_own_properties(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner', 'email' => 'other-owner@example.test', 'password_changed_at' => now()]);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->firstOrFail()->id);

        $other = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Someone Else Home',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 700,
            'status' => 'occupied',
            'suburb' => 'Kumalo',
            'city' => 'Bulawayo',
        ]);
        MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $other->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'structural',
            'priority' => 'low',
            'title' => 'Crack in the wall',
            'description' => 'A hairline crack appeared above the door.',
            'status' => 'reported',
            'sla_due_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->owner())
            ->get('/owner/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Maintenance/Index')
                ->where('requests.total', 2)
                ->where('requests.data.0.property.title', '4 Bedroom Family Home in Kumalo'));
    }

    public function test_tenant_page_reports_leased_properties_only(): void
    {
        $this->actingAs($this->tenant())
            ->get('/tenant/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Maintenance')
                ->where('requests.total', 2)
                ->has('properties')
                ->has('categories'));
    }

    public function test_escalation_state_of_seeded_emergency_is_badged(): void
    {
        $emergency = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->assertSame('emergency', $emergency->priority);
        $this->assertSame('reported', $emergency->status);
        $this->assertNotNull($emergency->escalated_at);
        $this->assertTrue($emergency->sla_due_at->isPast());
    }

    public function test_request_state_machine_matches_module_spec(): void
    {
        $this->assertSame(
            ['reported', 'assigned', 'in_progress', 'completed', 'closed', 'declined'],
            array_keys(MaintenanceRequest::STATUSES)
        );
        $this->assertSame(['low', 'medium', 'high', 'emergency'], MaintenanceRequest::PRIORITIES);
        $this->assertTrue(in_array('declined', MaintenanceRequest::TRANSITIONS['reported'], true));
        $this->assertTrue(in_array('completed', MaintenanceRequest::TRANSITIONS['in_progress'], true));
        $this->assertSame(['closed', 'declined'], MaintenanceRequest::TERMINAL);
    }

    // ---------- contractor registry + assignment (M11, Wave 5 slice 2) ----------

    public function test_admin_registers_contractor_into_vetting_then_verifies(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/contractors', [
                'business_name' => 'Test Electricians',
                'contact' => '0711 000 111',
                'service_area' => ['Gweru'],
                'trades' => [['trade' => 'Electrical', 'rate' => '35.5']],
            ])
            ->assertRedirect();

        $contractor = Contractor::where('business_name', 'Test Electricians')->firstOrFail();
        $this->assertSame('vetting', $contractor->status);
        $this->assertNull($contractor->verified_at);
        $this->assertDatabaseHas('contractor_trades', ['contractor_id' => $contractor->id, 'trade' => 'Electrical', 'rate' => '35.50']);

        $this->actingAs($this->admin())
            ->post(route('admin.contractors.status', ['contractor' => $contractor->id]), ['status' => 'verified'])
            ->assertRedirect();

        $this->assertSame('verified', $contractor->fresh()->status);
        $this->assertNotNull($contractor->fresh()->verified_at);
    }

    public function test_owner_assigns_verified_contractor_with_quote(): void
    {
        Notification::fake();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->assertSame('reported', $request->status);
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => $contractor->id,
                'approved_quote' => '85',
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('assigned', $request->status);
        $this->assertSame('85.00', $request->approved_quote);
        $this->assertSame($contractor->id, (int) $request->assigned_contractor_id);
        $this->assertNull($request->escalated_at, 'assigning clears the staff escalation flag');
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'assigned', 'actor_id' => $this->owner()->id]);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'assigned', 'notes' => '$85.00 — Bulawayo Plumbing Co.']);

        $contractorUser = User::where('username', 'contractor')->firstOrFail();
        Notification::assertSentTo($contractorUser, MaintenanceAssignedNotification::class);
    }

    public function test_only_verified_contractors_can_be_assigned(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $vetting = Contractor::where('business_name', 'CleanFlow Pest Solutions')->firstOrFail();

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => $vetting->id,
                'approved_quote' => '50',
            ])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('contractor_id');

        $this->assertSame('reported', $request->fresh()->status);
    }

    public function test_double_assign_is_blocked(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $contractor = Contractor::where('business_name', 'Mura Building Services')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => $contractor->id,
                'approved_quote' => '120',
            ])
            ->assertRedirect();
        $this->assertSame('assigned', $request->fresh()->status);

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => $contractor->id,
                'approved_quote' => '130',
            ])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');
    }

    public function test_owner_cannot_assign_another_owners_request(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner Two', 'email' => 'other-owner-2@example.test', 'password_changed_at' => now()]);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->firstOrFail()->id);

        $other = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign Home',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 650,
            'status' => 'occupied',
            'suburb' => 'Mpopoma',
            'city' => 'Bulawayo',
        ]);
        $foreign = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $other->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Leaking pipe',
            'description' => 'Water seeps under the sink.',
            'status' => 'reported',
            'sla_due_at' => now()->addHours(96),
        ]);
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $foreign->id]), [
                'contractor_id' => $contractor->id,
                'approved_quote' => '40',
            ])
            ->assertNotFound();

        $this->assertSame('reported', $foreign->fresh()->status);
    }

    public function test_assign_requires_a_valid_quote(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), ['contractor_id' => $contractor->id])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('approved_quote');

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), ['contractor_id' => $contractor->id, 'approved_quote' => '-5'])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('approved_quote');

        $this->assertSame('reported', $request->fresh()->status);
    }

    public function test_contractor_jobs_page_shows_their_assigned_briefs_only(): void
    {
        $contractor = User::where('username', 'contractor')->firstOrFail();
        $this->actingAs($contractor)
            ->get('/contractor/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Contractor/Maintenance/Index')
                ->where('requests.total', 1)
                ->where('requests.data.0.request_no', 'MR-2026-00001')
                ->where('requests.data.0.approved_quote', '85.00'));

        $stranger = User::factory()->create(['name' => 'Stranger Contractor', 'email' => 'contractor-stranger@example.test', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Contractor')->firstOrFail()->id);
        $this->actingAs($stranger)
            ->get('/contractor/maintenance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('requests.total', 0));
    }

    public function test_registry_transitions_follow_the_state_machine(): void
    {
        $vetting = Contractor::where('business_name', 'CleanFlow Pest Solutions')->firstOrFail();
        $this->actingAs($this->admin())
            ->from('/admin/contractors')
            ->post(route('admin.contractors.status', ['contractor' => $vetting->id]), ['status' => 'suspended'])
            ->assertRedirect('/admin/contractors')
            ->assertSessionHasErrors('status');
        $this->assertSame('vetting', $vetting->fresh()->status);
    }

    public function test_suspended_contractors_receive_no_new_assignments(): void
    {
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();
        $this->actingAs($this->admin())
            ->post(route('admin.contractors.status', ['contractor' => $contractor->id]), ['status' => 'suspended'])
            ->assertRedirect();
        $this->assertSame('suspended', $contractor->fresh()->status);
        $this->assertNull($contractor->fresh()->verified_at, 'leaving verified clears the verified stamp');

        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => $contractor->id,
                'approved_quote' => '40',
            ])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('contractor_id');
        $this->assertSame('reported', $request->fresh()->status);
    }

    // ---------- track & close (M10 slice 3, Wave 5 slice 3) ----------

    public function test_contractor_starts_and_completes_their_job(): void
    {
        Notification::fake();
        $contractor = User::where('username', 'contractor')->firstOrFail();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $this->assertSame('assigned', $request->status);

        $this->actingAs($contractor)
            ->post(route('contractor.maintenance.start', ['maintenanceRequest' => $request->id]))
            ->assertRedirect();

        $this->assertSame('in_progress', $request->fresh()->status);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'started', 'actor_id' => $contractor->id]);
        Notification::assertSentTo($this->owner(), MaintenanceStartedNotification::class);

        $this->actingAs($contractor)
            ->from('/contractor/maintenance')
            ->post(route('contractor.maintenance.complete', ['maintenanceRequest' => $request->id]), ['notes' => 'Replaced the washer and resealed the basin.'], )
            ->assertRedirect('/contractor/maintenance');

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'completed', 'actor_id' => $contractor->id, 'notes' => 'Replaced the washer and resealed the basin.']);
        Notification::assertSentTo($this->owner(), MaintenanceWorkCompletedNotification::class);
        Notification::assertSentTo($this->tenant(), MaintenanceWorkCompletedNotification::class);
    }

    public function test_contractor_cannot_work_another_contractors_job(): void
    {
        $stranger = User::factory()->create(['name' => 'Stranger Contractor', 'email' => 'contractor-rogue@example.test', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Contractor')->firstOrFail()->id);

        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('contractor.maintenance.start', ['maintenanceRequest' => $request->id]))
            ->assertNotFound();
        $this->assertSame('assigned', $request->fresh()->status);
    }

    public function test_lifecycle_transitions_are_gated(): void
    {
        $contractor = User::where('username', 'contractor')->firstOrFail();
        $profile = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();

        // Start requires `assigned`.
        $request->update(['status' => 'reported']);
        $this->actingAs($contractor)
            ->from('/contractor/maintenance')
            ->post(route('contractor.maintenance.start', ['maintenanceRequest' => $request->id]))
            ->assertRedirect('/contractor/maintenance')
            ->assertSessionHasErrors('request');
        $this->assertSame('reported', $request->fresh()->status);

        // Complete requires `in_progress` AND a summary.
        $request->update(['status' => 'assigned']);
        $this->actingAs($contractor)
            ->from('/contractor/maintenance')
            ->post(route('contractor.maintenance.complete', ['maintenanceRequest' => $request->id]), ['notes' => 'Done.'])
            ->assertRedirect('/contractor/maintenance')
            ->assertSessionHasErrors('request');

        $request->update(['status' => 'in_progress', 'assigned_contractor_id' => $profile->id]);
        $this->actingAs($contractor)
            ->from('/contractor/maintenance')
            ->post(route('contractor.maintenance.complete', ['maintenanceRequest' => $request->id]), [])
            ->assertRedirect('/contractor/maintenance')
            ->assertSessionHasErrors('notes');
        $this->assertSame('in_progress', $request->fresh()->status);
    }

    public function test_tenant_confirms_the_fix_once_then_owner_closes(): void
    {
        Notification::fake();
        $contractorUser = User::where('username', 'contractor')->firstOrFail();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'completed']);

        $this->actingAs($this->tenant())
            ->from('/tenant/maintenance')
            ->post(route('tenant.maintenance.confirm', ['maintenanceRequest' => $request->id]))
            ->assertRedirect('/tenant/maintenance');

        $this->assertNotNull($request->fresh()->tenant_confirmed_at);
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'tenant_confirmed', 'actor_id' => $this->tenant()->id]);
        Notification::assertSentTo($this->owner(), MaintenanceTenantConfirmedNotification::class);

        // Confirmation is a one-shot gate — a second confirm is rejected.
        $this->actingAs($this->tenant())
            ->from('/tenant/maintenance')
            ->post(route('tenant.maintenance.confirm', ['maintenanceRequest' => $request->id]))
            ->assertRedirect('/tenant/maintenance')
            ->assertSessionHasErrors('request');

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.close', ['maintenanceRequest' => $request->id]), ['notes' => 'All good.'])
            ->assertRedirect('/owner/maintenance');

        $request->refresh();
        $this->assertSame('closed', $request->status);
        $this->assertNotNull($request->resolved_at);                    // resolved lifecycle (AC-04)
        $this->assertSame('85.00', $request->approved_quote);           // quote retained → maintenance spend
        $this->assertDatabaseHas('maintenance_actions', ['request_id' => $request->id, 'action' => 'closed', 'notes' => 'All good.']);
        Notification::assertSentTo($this->tenant(), MaintenanceClosedNotification::class);
        Notification::assertSentTo($contractorUser, MaintenanceClosedNotification::class);
    }

    public function test_tenant_confirm_gates_close(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'completed']);

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.close', ['maintenanceRequest' => $request->id]))
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertNull($request->fresh()->resolved_at);
    }

    public function test_tenant_can_only_confirm_their_own_completed_request(): void
    {
        $stranger = $this->strangerTenant();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'completed']);

        $this->actingAs($stranger)
            ->post(route('tenant.maintenance.confirm', ['maintenanceRequest' => $request->id]))
            ->assertNotFound();

        // Confirming uncompleted work is rejected too.
        $reported = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->actingAs($this->tenant())
            ->from('/tenant/maintenance')
            ->post(route('tenant.maintenance.confirm', ['maintenanceRequest' => $reported->id]))
            ->assertRedirect('/tenant/maintenance')
            ->assertSessionHasErrors('request');
    }

    public function test_owner_cannot_close_another_owners_request(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner Three', 'email' => 'other-owner-3@example.test', 'password_changed_at' => now()]);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->firstOrFail()->id);

        $other = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign Home Two',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 650,
            'status' => 'occupied',
            'suburb' => 'Mpopoma',
            'city' => 'Bulawayo',
        ]);
        $foreign = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad(Str::random(4), 5, '0'),
            'property_id' => $other->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Leaking pipe',
            'description' => 'Water seeps under the sink.',
            'status' => 'completed',
            'tenant_confirmed_at' => now(),
            'sla_due_at' => now()->addHours(96),
        ]);

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.close', ['maintenanceRequest' => $foreign->id]))
            ->assertNotFound();

        $this->assertSame('completed', $foreign->fresh()->status);
    }

    // ---------- Wave 5 slice 4: contractor ratings ----------

    public function test_owner_rates_contractor_after_close(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();

        // Drive the seeded job through the whole lifecycle to closed.
        $this->actingAs($contractor->user)
            ->post(route('contractor.maintenance.start', ['maintenanceRequest' => $request->id]))
            ->assertRedirect();
        $this->actingAs($contractor->user)
            ->post(route('contractor.maintenance.complete', ['maintenanceRequest' => $request->id]), ['notes' => 'Replaced the coupling.'])
            ->assertRedirect();
        $this->actingAs($this->tenant())
            ->post(route('tenant.maintenance.confirm', ['maintenanceRequest' => $request->id]))
            ->assertRedirect();
        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.close', ['maintenanceRequest' => $request->id]))
            ->assertRedirect();

        Notification::fake();

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), ['rating' => 4, 'note' => 'Tidy work, on time.'])
            ->assertRedirect();

        $this->assertDatabaseHas('contractor_ratings', [
            'request_id' => $request->id,
            'contractor_id' => $contractor->id,
            'owner_id' => $this->owner()->id,
            'rating' => 4,
            'note' => 'Tidy work, on time.',
        ]);
        $this->assertDatabaseHas('maintenance_actions', [
            'request_id' => $request->id,
            'action' => 'rated',
            'notes' => '4/5 — Tidy work, on time.',
        ]);
        // Seeded aggregate was 4.80 over 12 jobs: (4.80 * 12 + 4) / 13 = 4.74.
        $this->assertSame(13, (int) $contractor->fresh()->jobs_completed);
        $this->assertSame('4.74', (string) $contractor->fresh()->rating_avg);
        Notification::assertSentTo($contractor->user, ContractorRatedNotification::class);
    }

    public function test_rating_requires_a_closed_job(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'in_progress']);

        // A live job (in progress) cannot be rated.
        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), ['rating' => 5])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');

        // Completed but NOT yet closed (ratings reflect completed jobs only).
        $request->update(['status' => 'completed', 'tenant_confirmed_at' => now()]);
        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), ['rating' => 5])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');

        // A declined job never had a contractor to rate.
        $declined = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $declined->update(['status' => 'declined', 'assigned_contractor_id' => null]);
        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $declined->id]), ['rating' => 5])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');

        $this->assertSame(0, ContractorRating::query()->count());
    }

    public function test_one_rating_per_request(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'closed', 'tenant_confirmed_at' => now()]);

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), ['rating' => 5])
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->from('/owner/maintenance')
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), ['rating' => 2])
            ->assertRedirect('/owner/maintenance')
            ->assertSessionHasErrors('request');

        $this->assertSame(1, ContractorRating::query()->where('request_id', $request->id)->count());
        $this->assertSame(13, (int) Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail()->jobs_completed);
    }

    public function test_rating_recomputes_the_average_correctly(): void
    {
        $property = Property::where('owner_id', $this->owner()->id)->firstOrFail();

        $established = Contractor::create([
            'business_name' => 'Fresh Hearts Electrical',
            'contact' => '0712 000 111',
            'service_area' => ['Bulawayo'],
            'status' => 'verified',
            'rating_avg' => 3.00,
            'jobs_completed' => 5,
            'verified_at' => now(),
        ]);
        $sequence = MaintenanceRequest::query()->max('id');
        $first = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad((string) ($sequence + 1), 5, '0', STR_PAD_LEFT),
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'electrical',
            'priority' => 'low',
            'title' => 'Socket replaced',
            'description' => 'Loose socket in the lounge.',
            'status' => 'closed',
            'assigned_contractor_id' => $established->id,
            'tenant_confirmed_at' => now(),
            'sla_due_at' => now()->addHours(168),
        ]);

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $first->id]), ['rating' => 4])
            ->assertRedirect();
        // (3.00 * 5 + 4) / 6 = 3.1667 -> 3.17, jobs 6.
        $this->assertSame(6, (int) $established->fresh()->jobs_completed);
        $this->assertSame('3.17', (string) $established->fresh()->rating_avg);

        // A brand-new contractor with no history: first rating stands alone.
        $fresh = Contractor::create([
            'business_name' => 'Tapiwa Fixers',
            'contact' => '0771 555 000',
            'service_area' => ['Bulawayo'],
            'status' => 'verified',
            'rating_avg' => 0,
            'jobs_completed' => 0,
            'verified_at' => now(),
        ]);
        $second = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad((string) ($sequence + 2), 5, '0', STR_PAD_LEFT),
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'appliance',
            'priority' => 'low',
            'title' => 'Geyser element',
            'description' => 'Element tripped.',
            'status' => 'closed',
            'assigned_contractor_id' => $fresh->id,
            'tenant_confirmed_at' => now(),
            'sla_due_at' => now()->addHours(168),
        ]);

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $second->id]), ['rating' => 5, 'note' => 'Excellent service.'])
            ->assertRedirect();
        $this->assertSame(1, (int) $fresh->fresh()->jobs_completed);
        $this->assertSame('5.00', (string) $fresh->fresh()->rating_avg);
    }

    public function test_rating_validation(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->update(['status' => 'closed', 'tenant_confirmed_at' => now()]);

        $payloads = [
            ['rating' => '', 'note' => null],
            ['rating' => 0, 'note' => null],
            ['rating' => 6, 'note' => null],
            ['rating' => 'good', 'note' => null],
            ['rating' => 4, 'note' => str_repeat('a', 501)],
        ];

        foreach ($payloads as $payload) {
            $this->actingAs($this->owner())
                ->from('/owner/maintenance')
                ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $request->id]), $payload)
                ->assertRedirect('/owner/maintenance')
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, ContractorRating::query()->where('request_id', $request->id)->count());
    }

    public function test_owner_cannot_rate_another_owners_request(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner Four', 'email' => 'other-owner-4@example.test', 'password_changed_at' => now()]);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->firstOrFail()->id);

        $other = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign Home Three',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850,
            'status' => 'occupied',
            'suburb' => 'Nkulumane',
            'city' => 'Bulawayo',
        ]);
        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->firstOrFail();
        $foreign = MaintenanceRequest::create([
            'request_no' => 'MR-2026-'.str_pad((string) (MaintenanceRequest::query()->max('id') + 1), 5, '0', STR_PAD_LEFT),
            'property_id' => $other->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Leaking toilet',
            'description' => 'Runs continuously.',
            'status' => 'closed',
            'assigned_contractor_id' => $contractor->id,
            'tenant_confirmed_at' => now(),
            'sla_due_at' => now()->addHours(96),
        ]);

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.rate', ['maintenanceRequest' => $foreign->id]), ['rating' => 5])
            ->assertNotFound();

        $this->assertSame(0, ContractorRating::query()->where('request_id', $foreign->id)->count());
        $this->assertSame(12, (int) $contractor->fresh()->jobs_completed);
    }
}