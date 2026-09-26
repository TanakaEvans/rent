<?php

namespace Tests\Feature\Fixes;

use App\Models\AdPackage;
use App\Models\ConfigurationAudit;
use App\Models\Contractor;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Report;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Notifications\EmergencyMaintenanceNotification;
use App\Notifications\MaintenanceTenantUpdateNotification;
use App\Services\AdPlacementService;
use App\Services\ConfigurationService;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the operations area: contractor registry, maintenance
 * escalation + tracking, advertising, the Configuration Centre, the KYC
 * review page and the listing-report moderation queue.
 */
class OperationsFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ---------- helpers ----------

    private function admin(): User
    {
        return User::where('email', 'admin@system.local')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('email', 'staff@dzimba.local')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'owner@dzimba.local')->firstOrFail();
    }

    private function tenant(): User
    {
        return User::where('email', 'tenant@dzimba.local')->firstOrFail();
    }

    private function contractorUser(): User
    {
        return User::where('email', 'contractor@dzimba.local')->firstOrFail();
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['name' => 'Ops '.$role, 'email' => $email, 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        return $user;
    }

    private function rentedHome(): Property
    {
        return Property::where('title', '4 Bedroom Family Home in Kumalo')->firstOrFail();
    }

    private function newRequest(array $overrides = []): MaintenanceRequest
    {
        return MaintenanceRequest::create(array_merge([
            'request_no' => 'MR-T-'.Str::upper(Str::random(8)),
            'property_id' => $this->rentedHome()->id,
            'tenant_id' => $this->tenant()->id,
            'category' => 'plumbing',
            'priority' => 'medium',
            'title' => 'Dripping tap',
            'description' => 'The bathroom tap drips.',
            'status' => 'reported',
            'sla_due_at' => now()->addDay(),
        ], $overrides));
    }

    private function updateConfig(string $key, string $value): void
    {
        SystemConfiguration::where('key', $key)->update(['value' => $value]);
        Cache::forget('dzimba.config.'.$key);
    }

    private function adProperty(User $owner): Property
    {
        return Property::create([
            'owner_id' => $owner->id,
            'title' => 'Ops Ad House '.Str::random(6),
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => 'available',
            'suburb' => 'Borrowdale',
            'city' => 'Harare',
        ]);
    }

    /**
     * A fresh owner with an empty promotion budget (the demo owner already
     * holds seeded placements).
     */
    private function adOwner(): User
    {
        return $this->userWithRole('Owner', 'ops-ad-owner@example.test');
    }

    private function package(): AdPackage
    {
        return AdPackage::where('code', 'featured-property')->firstOrFail();
    }

    private function ads(): AdPlacementService
    {
        return app(AdPlacementService::class);
    }

    // ---------- 1. contractor registry transitions ----------

    public function test_every_contractor_transition_works_with_the_status_key_the_ui_sends(): void
    {
        foreach (Contractor::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $contractor = Contractor::create([
                    'business_name' => "Ops {$from} to {$to}",
                    'contact' => '0700 000 000',
                    'service_area' => ['Harare'],
                    'status' => $from,
                ]);

                $this->actingAs($this->admin())
                    ->from('/admin/contractors')
                    ->post(route('admin.contractors.status', ['contractor' => $contractor->id]), ['status' => $to])
                    ->assertRedirect('/admin/contractors')
                    ->assertSessionHasNoErrors();

                $this->assertSame($to, $contractor->fresh()->status, "{$from} -> {$to} must be reachable from the registry.");
            }
        }
    }

    public function test_button_labels_are_not_valid_status_payloads(): void
    {
        $vetting = Contractor::where('business_name', 'CleanFlow Pest Solutions')->firstOrFail();

        $this->actingAs($this->admin())
            ->from('/admin/contractors')
            ->post(route('admin.contractors.status', ['contractor' => $vetting->id]), ['status' => 'Verify'])
            ->assertSessionHasErrors('status');

        $this->assertSame('vetting', $vetting->fresh()->status);
    }

    public function test_registry_page_exposes_transition_map_and_counts_over_all_profiles(): void
    {
        foreach (range(1, 13) as $i) {
            Contractor::create(['business_name' => "Bulk {$i}", 'contact' => 'x', 'service_area' => ['Harare'], 'status' => 'vetting']);
        }

        $this->actingAs($this->admin())
            ->get(route('admin.contractors.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Contractors/Index')
                ->where('transitions.vetting', ['verified', 'unverified'])
                ->where('transitions.suspended', ['verified', 'unverified'])
                ->where('stats.total', 16)
                ->where('stats.verified', 2)
                ->where('stats.under_review', 14)
                ->where('contractors.last_page', 2));
    }

    // ---------- 2. contractor <-> login ----------

    public function test_registering_with_a_login_email_links_the_account_and_grants_contractor_role(): void
    {
        $login = $this->userWithRole('Tenant', 'new-trade@example.test');

        $this->actingAs($this->admin())
            ->post(route('admin.contractors.store'), [
                'business_name' => 'Linked Electricians',
                'contact' => '0711 000 222',
                'service_area' => ['Harare'],
                'trades' => [['trade' => 'Electrical', 'rate' => '30']],
                'user_email' => 'NEW-TRADE@example.test',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $contractor = Contractor::where('business_name', 'Linked Electricians')->firstOrFail();
        $this->assertSame($login->id, $contractor->user_id);
        $this->assertTrue($login->fresh()->hasRole('Contractor'));

        $this->actingAs($login->fresh())
            ->get(route('contractor.maintenance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('profile.id', $contractor->id));
    }

    public function test_registering_with_an_unknown_email_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/contractors')
            ->post(route('admin.contractors.store'), [
                'business_name' => 'Ghost Builders',
                'contact' => '0711 000 333',
                'service_area' => ['Harare'],
                'trades' => [['trade' => 'Building', 'rate' => '']],
                'user_email' => 'nobody@example.test',
            ])
            ->assertSessionHasErrors('user_email');

        $this->assertDatabaseMissing('contractors', ['business_name' => 'Ghost Builders']);
    }

    public function test_admin_can_link_a_login_to_an_existing_profile_later(): void
    {
        $profile = Contractor::where('business_name', 'CleanFlow Pest Solutions')->firstOrFail();
        $login = User::factory()->create(['email' => 'pest-login@example.test', 'password_changed_at' => now()]);
        $login->roles()->attach(Role::where('name', 'Tenant')->firstOrFail()->id);

        $this->actingAs($this->admin())
            ->post(route('admin.contractors.link', ['contractor' => $profile->id]), ['user_email' => 'pest-login@example.test'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($login->id, $profile->fresh()->user_id);
        $this->assertTrue($login->fresh()->hasRole('Contractor'));
        $this->actingAs($login->fresh())->get(route('contractor.maintenance.index'))->assertOk();

        // Already linked, and an account already backing another profile, are both refused.
        $this->actingAs($this->admin())
            ->from('/admin/contractors')
            ->post(route('admin.contractors.link', ['contractor' => $profile->id]), ['user_email' => 'pest-login@example.test'])
            ->assertSessionHasErrors('user_email');

        $mura = Contractor::where('business_name', 'Mura Building Services')->firstOrFail();
        $this->actingAs($this->admin())
            ->from('/admin/contractors')
            ->post(route('admin.contractors.link', ['contractor' => $mura->id]), ['user_email' => 'contractor@dzimba.local'])
            ->assertSessionHasErrors('user_email');
        $this->assertNull($mura->fresh()->user_id);
    }

    public function test_only_staff_can_link_contractor_logins(): void
    {
        $profile = Contractor::where('business_name', 'Mura Building Services')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('admin.contractors.link', ['contractor' => $profile->id]), ['user_email' => 'tenant@dzimba.local'])
            ->assertForbidden();

        $this->actingAs($this->staff())
            ->post(route('admin.contractors.link', ['contractor' => $profile->id]), ['user_email' => 'tenant@dzimba.local'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->tenant()->hasRole('Contractor'));
    }

    // ---------- 3/4. ad placement pause/resume ----------

    public function test_resume_extends_the_window_by_exactly_the_paused_span(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $owner = $this->adOwner();
        $placement = $this->ads()->book($owner, $this->adProperty($owner), $this->package());
        $this->ads()->approve($placement);
        $originalEnd = $placement->fresh()->ends_at->copy();

        $this->ads()->pause($placement->fresh());
        Carbon::setTestNow('2026-09-04 14:30:00');
        $resumed = $this->ads()->resume($placement->fresh());

        $pausedSeconds = 3 * 86400 + 6 * 3600 + 30 * 60;
        $this->assertSame($originalEnd->getTimestamp() + $pausedSeconds, $resumed->ends_at->getTimestamp());
        $this->assertTrue($resumed->ends_at->greaterThan($originalEnd));
        Carbon::setTestNow();
    }

    public function test_pause_and_resume_keep_the_moderator_note(): void
    {
        $owner = $this->adOwner();
        $placement = $this->ads()->book($owner, $this->adProperty($owner), $this->package());
        $this->ads()->approve($placement);

        $this->actingAs($this->admin())
            ->post(route('admin.advertising.pause', ['placement' => $placement->id]), ['note' => 'Photos under review'])
            ->assertRedirect();
        $this->assertSame('Photos under review', $placement->fresh()->admin_note);

        $this->actingAs($this->admin())
            ->post(route('admin.advertising.resume', ['placement' => $placement->id]), ['note' => 'Photos fixed'])
            ->assertRedirect();
        $this->assertSame('Photos fixed', $placement->fresh()->admin_note);
    }

    // ---------- 5. owner cancels a reserved placement ----------

    public function test_owner_cancels_their_own_reserved_placement_with_full_credit_and_can_rebook(): void
    {
        $this->updateConfig('featured.max_active_per_owner', '1');
        $owner = $this->adOwner();
        $property = $this->adProperty($owner);
        $placement = $this->ads()->book($owner, $property, $this->package());

        $this->actingAs($owner)
            ->post(route('owner.advertising.cancel', ['placement' => $placement->id]))
            ->assertRedirect(route('owner.advertising.index'))
            ->assertSessionHas('success');

        $fresh = $placement->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame(number_format((float) $placement->amount, 2, '.', ''), (string) $fresh->credit_amount);

        $rebooked = $this->ads()->book($owner, $property->fresh(), $this->package());
        $this->assertSame('reserved', $rebooked->status);
    }

    public function test_owner_cannot_cancel_a_live_placement_or_another_owners_order(): void
    {
        $owner = $this->adOwner();
        $live = $this->ads()->book($owner, $this->adProperty($owner), $this->package());
        $this->ads()->approve($live);

        $this->actingAs($owner)
            ->from(route('owner.advertising.index'))
            ->post(route('owner.advertising.cancel', ['placement' => $live->id]))
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $live->fresh()->status);

        $other = $this->userWithRole('Owner', 'ops-other-owner@example.test');
        $theirs = $this->ads()->book($other, $this->adProperty($other), $this->package());

        $this->actingAs($owner)
            ->post(route('owner.advertising.cancel', ['placement' => $theirs->id]))
            ->assertNotFound();
        $this->assertSame('reserved', $theirs->fresh()->status);
    }

    public function test_owner_cancel_route_is_owner_only(): void
    {
        $owner = $this->adOwner();
        $placement = $this->ads()->book($owner, $this->adProperty($owner), $this->package());
        $url = route('owner.advertising.cancel', ['placement' => $placement->id]);

        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($this->tenant())->post($url)->assertForbidden();
        $this->actingAs($this->contractorUser())->post($url)->assertForbidden();
        $this->assertSame('reserved', $placement->fresh()->status);
    }

    // ---------- 6. Configuration Centre saves only changed keys ----------

    public function test_saving_with_one_changed_key_writes_exactly_one_audit_row(): void
    {
        $payload = SystemConfiguration::where('group_name', 'subscriptions')->get()
            ->mapWithKeys(fn (SystemConfiguration $row) => [$row->key => match ($row->type) {
                'json' => json_decode($row->value, true),
                'boolean' => $row->value === '1',
                default => $row->value,
            }])
            ->all();
        $payload['subscriptions.grace_period_days'] = 10;
        $before = ConfigurationAudit::count();

        $this->actingAs($this->admin())
            ->patch(route('admin.configuration.update'), ['values' => $payload, 'reason' => 'Ten days of grace.'])
            ->assertRedirect(route('admin.configuration.index'))
            ->assertSessionHas('success', '1 configuration value(s) updated.');

        $this->assertSame($before + 1, ConfigurationAudit::count());
        $this->assertDatabaseHas('configuration_audits', ['key' => 'subscriptions.grace_period_days', 'new_value' => '10']);
    }

    public function test_setting_an_unchanged_value_is_not_audited(): void
    {
        $before = ConfigurationAudit::count();
        $current = SystemConfiguration::where('key', 'featured.default_price')->value('value');

        app(ConfigurationService::class)->set('featured.default_price', $current, $this->admin()->id);
        app(ConfigurationService::class)->set('marketplace.report_categories', json_decode(SystemConfiguration::where('key', 'marketplace.report_categories')->value('value'), true), $this->admin()->id);

        $this->assertSame($before, ConfigurationAudit::count());

        $this->actingAs($this->admin())
            ->patch(route('admin.configuration.update'), ['values' => ['featured.default_price' => $current]])
            ->assertSessionHas('success', 'No changes to save.');
        $this->assertSame($before, ConfigurationAudit::count());
    }

    public function test_configuration_page_shows_every_group_with_option_lists(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.configuration.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Configuration/Index')
                ->has('groups.invoices')
                ->has('groups.maintenance')
                ->has('groups.marketplace')
                ->has('groups.listings')
                ->where('options', fn ($options) => collect($options)->has([
                    'subscriptions.proration.mode',
                    'subscriptions.suspension.behaviour',
                    'invoices.timing',
                    'late_fees.type',
                ])));
    }

    // ---------- 7/8. option and type validation ----------

    public function test_option_keys_reject_values_outside_their_list(): void
    {
        $cases = [
            'subscriptions.proration.mode' => 'refund_everything',
            'invoices.timing' => 'whenever',
            'late_fees.type' => 'percentage',
            'subscriptions.suspension.behaviour' => 'suspend_premium',
        ];

        foreach ($cases as $key => $bad) {
            $stored = SystemConfiguration::where('key', $key)->value('value');

            $this->actingAs($this->admin())
                ->from(route('admin.configuration.index'))
                ->patch(route('admin.configuration.update'), ['values' => [$key => $bad]])
                ->assertSessionHasErrors('values.'.$key);

            $this->assertSame($stored, SystemConfiguration::where('key', $key)->value('value'), "{$key} must not accept {$bad}.");
        }

        $this->actingAs($this->admin())
            ->patch(route('admin.configuration.update'), ['values' => ['late_fees.type' => 'fixed']])
            ->assertSessionHasNoErrors();
        $this->assertSame('fixed', SystemConfiguration::where('key', 'late_fees.type')->value('value'));
    }

    public function test_suspension_behaviour_only_offers_implemented_options(): void
    {
        $this->assertSame(['keep_listings', 'hide_listings'], ConfigurationService::optionsFor('subscriptions.suspension.behaviour'));
        $this->assertContains(
            SystemConfiguration::where('key', 'subscriptions.suspension.behaviour')->value('value'),
            ConfigurationService::optionsFor('subscriptions.suspension.behaviour')
        );
        $this->assertStringNotContainsString('full_suspend', (string) SystemConfiguration::where('key', 'subscriptions.suspension.behaviour')->value('description'));
    }

    public function test_numeric_boolean_and_json_values_are_type_checked(): void
    {
        $invalid = [
            'subscriptions.grace_period_days' => ['12.5', '-1', 'abc', true],
            'featured.default_price' => ['free', '-5'],
            'featured.enabled' => ['maybe'],
            'marketplace.report_categories' => ['not json', 'true'],
        ];

        foreach ($invalid as $key => $values) {
            $stored = SystemConfiguration::where('key', $key)->value('value');
            foreach ($values as $value) {
                $this->actingAs($this->admin())
                    ->from(route('admin.configuration.index'))
                    ->patch(route('admin.configuration.update'), ['values' => [$key => $value]])
                    ->assertSessionHasErrors('values.'.$key);
                $this->assertSame($stored, SystemConfiguration::where('key', $key)->value('value'), "{$key} must reject ".var_export($value, true));
            }
        }

        $this->actingAs($this->admin())
            ->patch(route('admin.configuration.update'), ['values' => [
                'subscriptions.grace_period_days' => '9',
                'featured.default_price' => '12.5',
                'featured.enabled' => false,
                'marketplace.report_categories' => ['duplicate', 'fraud_concern'],
            ]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '4 configuration value(s) updated.');

        $this->assertSame('9', SystemConfiguration::where('key', 'subscriptions.grace_period_days')->value('value'));
        $this->assertSame('12.50', SystemConfiguration::where('key', 'featured.default_price')->value('value'));
        $this->assertSame('0', SystemConfiguration::where('key', 'featured.enabled')->value('value'));
        $this->assertSame(['duplicate', 'fraud_concern'], json_decode(SystemConfiguration::where('key', 'marketplace.report_categories')->value('value'), true));
    }

    // ---------- 9. acknowledged requests are never re-escalated ----------

    public function test_acknowledged_request_is_not_reescalated_by_the_sweep_and_stays_visible(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        $this->assertNotNull($request->escalated_at);

        $this->actingAs($this->admin())
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->travel(2)->days();
        $this->artisan('maintenance:escalate')->assertSuccessful();

        $this->assertNull($request->fresh()->escalated_at);
        $this->assertSame(1, MaintenanceAction::where('request_id', $request->id)->where('action', 'escalated')->count());

        $this->actingAs($this->admin())
            ->get(route('admin.maintenance.escalations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 0)
                ->where('acknowledged.total', 1)
                ->where('acknowledged.data.0.id', $request->id)
                ->where('acknowledged.data.0.acknowledgement.actor.name', $this->admin()->name));

        // Taking ownership twice is refused.
        $this->actingAs($this->staff())
            ->from(route('admin.maintenance.escalations.index'))
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertSessionHasErrors('request');
    }

    public function test_acknowledged_request_leaves_the_staff_desk_once_assigned(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();
        app(MaintenanceService::class)->acknowledge($request, $this->admin());

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => Contractor::where('business_name', 'Bulawayo Plumbing Co.')->value('id'),
                'approved_quote' => '120',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get(route('admin.maintenance.escalations.index'))
            ->assertInertia(fn ($page) => $page->where('acknowledged.total', 0));
    }

    // ---------- 10. emergencies are actionable immediately ----------

    public function test_emergency_request_appears_in_the_admin_queue_immediately(): void
    {
        Notification::fake();

        $this->actingAs($this->tenant())
            ->post(route('tenant.maintenance.store'), [
                'property_id' => $this->rentedHome()->id,
                'category' => 'electrical',
                'priority' => 'emergency',
                'title' => 'Sparking socket',
                'description' => 'The kitchen socket is sparking.',
            ])
            ->assertSessionHasNoErrors();

        $request = MaintenanceRequest::where('title', 'Sparking socket')->firstOrFail();
        $this->assertNull($request->escalated_at);

        Notification::assertSentTo($this->admin(), EmergencyMaintenanceNotification::class, function ($notification) {
            return str_starts_with($notification->toDatabase($this->admin())['link'], route('admin.maintenance.escalations.index'));
        });

        $this->actingAs($this->admin())
            ->get(route('admin.maintenance.escalations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('emergencies.total', 1)
                ->where('emergencies.data.0.id', $request->id));

        $this->actingAs($this->admin())
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get(route('admin.maintenance.escalations.index'))
            ->assertInertia(fn ($page) => $page
                ->where('emergencies.total', 0)
                ->where('acknowledged.data.0.id', $request->id));
    }

    public function test_non_emergency_requests_inside_sla_cannot_be_acknowledged(): void
    {
        $request = $this->newRequest(['priority' => 'high']);

        $this->actingAs($this->admin())
            ->from(route('admin.maintenance.escalations.index'))
            ->post(route('admin.maintenance.escalations.ack', ['maintenanceRequest' => $request->id]))
            ->assertSessionHasErrors('request');
    }

    // ---------- 11. pagination and counts over all rows ----------

    public function test_tenant_and_owner_lists_paginate_with_counts_over_every_row(): void
    {
        foreach (range(1, 15) as $i) {
            $this->newRequest(['title' => "Fault {$i}"]);
        }

        $this->actingAs($this->tenant())
            ->get(route('tenant.maintenance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 17)
                ->where('requests.per_page', 12)
                ->where('requests.last_page', 2)
                ->has('requests.links')
                ->where('stats.open', 16)
                ->where('stats.within_sla', 15)
                ->where('stats.escalated', 1)
                ->where('stats.closed', 0));

        $this->actingAs($this->tenant())
            ->get(route('tenant.maintenance.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page->has('requests.data', 5));

        $this->actingAs($this->owner())
            ->get(route('owner.maintenance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 17)
                ->where('requests.last_page', 2)
                ->where('stats.open', 17)
                ->where('stats.awaiting_assignment', 16)
                ->where('stats.breached', 1)
                ->where('stats.closed', 0));
    }

    public function test_contractor_jobs_list_paginates(): void
    {
        $profile = Contractor::where('user_id', $this->contractorUser()->id)->firstOrFail();
        foreach (range(1, 12) as $i) {
            $this->newRequest(['title' => "Job {$i}", 'status' => 'assigned', 'assigned_contractor_id' => $profile->id, 'approved_quote' => '10.00']);
        }

        $this->actingAs($this->contractorUser())
            ->get(route('contractor.maintenance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 13)
                ->where('requests.last_page', 2)
                ->has('requests.data', 12));
    }

    // ---------- 12. contractor sees the assignment time ----------

    public function test_contractor_jobs_show_when_the_job_was_assigned_not_reported(): void
    {
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00001')->firstOrFail();
        $request->forceFill(['created_at' => Carbon::parse('2026-08-01 09:00:00')])->saveQuietly();
        MaintenanceAction::where('request_id', $request->id)->where('action', 'assigned')
            ->update(['created_at' => Carbon::parse('2026-08-03 15:45:00')]);

        $this->actingAs($this->contractorUser())
            ->get(route('contractor.maintenance.index'))
            ->assertInertia(fn ($page) => $page
                ->where('requests.data.0.id', $request->id)
                ->where('requests.data.0.assigned_at', fn ($value) => Carbon::parse($value)->equalTo(Carbon::parse('2026-08-03 15:45:00'))));
    }

    // ---------- 13. KYC tiers ----------

    public function test_kyc_rows_only_use_tiers_the_page_knows_how_to_label(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.kyc.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                $known = array_column($props['options']['tiers'], 'key');
                $this->assertSame(['none', 'basic', 'full'], $known);
                $this->assertNotEmpty($props['rows']['data']);
                foreach ($props['rows']['data'] as $row) {
                    $this->assertContains($row['kyc_tier'], $known);
                }
                $this->assertContains('full', array_column($props['rows']['data'], 'kyc_tier'), 'Seeded owner has both documents approved.');
            });
    }

    // ---------- 14. report priority filter ----------

    public function test_report_priority_filter_is_validated(): void
    {
        $property = $this->rentedHome();
        foreach (['low', 'high', 'high'] as $priority) {
            Report::create([
                'reporter_id' => $this->tenant()->id,
                'subject_type' => 'property',
                'subject_id' => $property->id,
                'category' => 'duplicate',
                'description' => "A {$priority} report",
                'status' => 'open',
                'priority' => $priority,
            ]);
        }
        $all = Report::count();

        $count = fn (array $query) => count($this->actingAs($this->admin())
            ->get(route('admin.marketplace.reports.index', $query))
            ->assertOk()
            ->viewData('page')['props']['reports']);

        $this->assertSame(Report::where('priority', 'high')->count(), $count(['priority' => 'high']));
        $this->assertSame(1, $count(['priority' => 'low']));
        $this->assertSame($all, $count(['priority' => 'bogus']), 'An unknown priority is ignored, not used as a filter.');
        $this->assertSame($all, $count(['priority' => ['high']]), 'An array priority is ignored.');
        $this->assertSame($all, $count(['status' => ['open']]), 'An array status is ignored.');
    }

    // ---------- 15. owner assign list shows trades and areas ----------

    public function test_owner_assign_list_carries_trades_and_service_areas(): void
    {
        $this->actingAs($this->owner())
            ->get(route('owner.maintenance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contractors', function ($contractors) {
                    $plumbing = collect($contractors)->firstWhere('business_name', 'Bulawayo Plumbing Co.');

                    return $plumbing !== null
                        && $plumbing['service_area'] === ['Bulawayo']
                        && collect($plumbing['trades'])->pluck('trade')->sort()->values()->all() === ['Electrical', 'Plumbing'];
                }));
    }

    // ---------- 16. tenant notified on assign + start ----------

    public function test_tenant_is_notified_when_a_contractor_is_assigned_and_starts_work(): void
    {
        Notification::fake();
        $request = MaintenanceRequest::where('request_no', 'MR-2026-00002')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('owner.maintenance.assign', ['maintenanceRequest' => $request->id]), [
                'contractor_id' => Contractor::where('business_name', 'Bulawayo Plumbing Co.')->value('id'),
                'approved_quote' => '95',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($this->tenant(), MaintenanceTenantUpdateNotification::class, function ($notification) use ($request) {
            $data = $notification->toDatabase($this->tenant());

            return $notification->stage === 'assigned'
                && $notification->request->id === $request->id
                && $data['link'] === route('tenant.maintenance.index')
                && str_contains($data['body'], 'Bulawayo Plumbing Co.');
        });

        $this->actingAs($this->contractorUser())
            ->post(route('contractor.maintenance.start', ['maintenanceRequest' => $request->id]))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($this->tenant(), MaintenanceTenantUpdateNotification::class, fn ($notification) => $notification->stage === 'started'
            && $notification->toDatabase($this->tenant())['title'] === 'Repair work has started');
        Notification::assertSentToTimes($this->tenant(), MaintenanceTenantUpdateNotification::class, 2);
    }
}
