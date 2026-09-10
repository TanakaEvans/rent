<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentInvoice;
use App\Models\RentSchedule;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Notifications\ReceiptIssuedNotification;
use App\Notifications\RentInvoiceDueNotification;
use App\Notifications\RentInvoiceOverdueNotification;
use App\Services\ConfigurationService;
use App\Services\FinancialSummaryService;
use App\Services\LeaseService;
use App\Services\PaymentService;
use App\Services\RentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MonetiseTest extends TestCase
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

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function newOwner(): User
    {
        $user = User::factory()->create(['name' => 'Fresh Owner', 'email' => 'fresh-owner@example.test', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Owner')->first()->id);

        return $user;
    }

    private function plan(string $name): SubscriptionPlan
    {
        return SubscriptionPlan::where('name', $name)->firstOrFail();
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function rentService(): RentService
    {
        return app(RentService::class);
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->first();
    }

    private function activeLease(?Carbon $start = null, ?Carbon $end = null): Lease
    {
        $property = $this->makeAvailableProperty($this->owner());

        return Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-'.strtoupper(Str::random(6)),
            'start_date' => $start ?? now(),
            'end_date' => $end ?? now()->addMonths(2)->endOfMonth(),
            'rent_amount' => $property->price,
            'deposit_amount' => $property->deposit ?? 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
    }

    private function propertyPayload(string $status = 'available'): array
    {
        return [
            'title' => 'Test House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => $status,
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
        ];
    }

    private function makeAvailableProperty(User $owner): Property
    {
        return Property::create([
            'owner_id' => $owner->id,
            'title' => 'Test House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => 'available',
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
        ]);
    }

    public function test_owner_without_subscription_is_lazily_placed_on_free_plan(): void
    {
        $owner = $this->newOwner();

        $response = $this->actingAs($owner)->get('/owner/subscriptions');

        $response->assertOk()->assertInertia(fn ($page) => $page->component('Owner/Subscriptions/Index'));

        $subscription = $owner->currentSubscription();
        $this->assertNotNull($subscription);
        $this->assertSame('Free', $subscription->plan->name);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertTrue($subscription->history()->where('event', 'subscribed')->exists());
    }

    public function test_second_publish_is_blocked_on_free_plan_until_upgrade(): void
    {
        $owner = $this->newOwner();
        $this->makeAvailableProperty($owner);

        $this->actingAs($owner)
            ->post('/owner/properties', $this->propertyPayload())
            ->assertSessionHasErrors('status');

        $this->assertSame(1, Property::where('owner_id', $owner->id)->where('status', 'available')->count());

        $this->service()->subscribe($owner, $this->plan('Basic')->id);

        $this->actingAs($owner)
            ->post('/owner/properties', $this->propertyPayload())
            ->assertRedirect();

        $this->assertSame(2, Property::where('owner_id', $owner->id)->where('status', 'available')->count());
    }

    public function test_returning_a_listing_to_available_is_quota_gated(): void
    {
        $owner = $this->newOwner();
        $this->makeAvailableProperty($owner);

        $occupied = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Test Flat',
            'property_type' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500.00,
            'status' => 'occupied',
        ]);

        $this->actingAs($owner)
            ->put('/owner/properties/'.$occupied->id.'/status', ['status' => 'available'])
            ->assertSessionHasErrors('status');

        $this->assertSame('occupied', $occupied->fresh()->status);
    }

    public function test_upgrade_prorates_the_remaining_cycle_value(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);
        $this->assertSame('Basic', $subscription->plan->name);

        $this->travel(15)->days();

        $upgraded = $this->service()->subscribe($owner, $this->plan('Professional')->id);

        $this->assertSame('Professional', $upgraded->plan->name);
        $this->assertSame('active', $upgraded->status);

        $invoice = $upgraded->invoices()->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertSame('12.50', $invoice->amount);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->receipt_no);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('proration', $invoice->details['event']);
        $this->assertSame('2.50', $invoice->details['credit']);

        $this->assertTrue($upgraded->history()->where('event', 'upgraded')->exists());
    }

    public function test_charge_difference_proration_mode_charges_the_plan_difference(): void
    {
        app(ConfigurationService::class)->set(
            'subscriptions.proration.mode',
            'charge_difference',
            $this->admin()->id,
            'Charge the price difference on upgrades.',
            $this->admin()->id
        );

        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);

        $this->travel(15)->days();

        $upgraded = $this->service()->subscribe($owner, $this->plan('Professional')->id);

        $this->assertSame('Professional', $upgraded->plan->name);

        $invoice = $upgraded->invoices()->latest()->first();
        $this->assertSame('10.00', $invoice->amount);
        $this->assertSame('proration', $invoice->details['event']);
        $this->assertSame('0.00', $invoice->details['credit']);
    }

    public function test_apply_at_renewal_proration_defers_the_upgrade_to_the_cycle_boundary(): void
    {
        app(ConfigurationService::class)->set(
            'subscriptions.proration.mode',
            'apply_at_renewal',
            $this->admin()->id,
            'Defer plan changes to the end of the cycle.',
            $this->admin()->id
        );

        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);
        $invoiceCountBefore = $subscription->invoices()->count();

        $this->travel(15)->days();

        $deferred = $this->service()->subscribe($owner, $this->plan('Professional')->id);

        $this->assertSame('Basic', $deferred->plan->name);
        $this->assertSame($this->plan('Professional')->id, $deferred->pendingUpgradeTo());
        $this->assertTrue($deferred->history()->where('event', 'upgrade_pending')->exists());
        $this->assertSame($invoiceCountBefore, $deferred->invoices()->count());

        $deferred->update(['ends_at' => now()->subDay()]);
        $this->service()->runExpiryCheck();

        $settled = $deferred->fresh();
        $this->assertSame('Professional', $settled->plan->name);
        $this->assertSame('active', $settled->status);
        $this->assertNull($settled->pendingUpgradeTo());
        $this->assertTrue($settled->ends_at->isFuture());
        $this->assertTrue($settled->history()->where('event', 'upgraded')->where('details->at', 'cycle_end')->exists());
    }

    public function test_downgrade_is_deferred_to_the_end_of_the_cycle(): void
    {
        $owner = $this->newOwner();

        $this->service()->subscribe($owner, $this->plan('Professional')->id);
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);

        $this->assertSame('Professional', $subscription->plan->name);
        $this->assertSame($this->plan('Basic')->id, $subscription->pendingDowngradeTo());
        $this->assertTrue($subscription->history()->where('event', 'downgrade_pending')->exists());

        $subscription->update(['ends_at' => now()->subDay()]);
        $this->service()->runExpiryCheck();

        $this->assertSame('Basic', $subscription->fresh()->plan->name);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertNull($subscription->fresh()->pendingDowngradeTo());
        $this->assertTrue($subscription->fresh()->ends_at->isFuture());
    }

    public function test_lapsed_cycle_moves_to_grace_then_suspension(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);

        $subscription->update(['ends_at' => now()->subDay()]);

        $this->service()->runExpiryCheck();
        $this->assertSame('grace', $subscription->fresh()->status);
        $this->assertTrue($subscription->history()->where('event', 'grace_period')->exists());
        $this->assertSame(now()->addDays(6)->toDateString(), $subscription->fresh()->graceUntil()->toDateString());

        $this->travel(8)->days();
        $this->service()->runExpiryCheck();

        $this->assertSame('suspended', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->history()->where('event', 'suspended')->exists());
    }

    public function test_suspended_owner_falls_back_to_free_plan_quota(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Basic')->id);
        $subscription->update(['ends_at' => now()->subDays(10)]);
        $this->service()->runExpiryCheck();
        $this->travel(8)->days();
        $this->service()->runExpiryCheck();
        $this->assertSame('suspended', $subscription->fresh()->status);

        $this->assertSame(1, $this->service()->effectiveLimit($owner));

        $properties = app(\App\Services\PropertyService::class);

        $properties->create($this->propertyPayload(), $owner);

        try {
            $properties->create($this->propertyPayload(), $owner);
            $this->fail('Expected ValidationException when publishing over the Free quota.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_fresh_paid_subscription_raises_a_single_settled_invoice(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->service()->subscribe($owner, $this->plan('Professional')->id);

        $this->assertSame('Professional', $subscription->plan->name);
        $this->assertSame(1, $subscription->invoices()->count());

        $invoice = $subscription->invoices()->first();
        $this->assertSame('15.00', $invoice->amount);
        $this->assertSame('paid', $invoice->status);
        $this->assertStringStartsWith('SUB-'.now()->year.'-', $invoice->invoice_no);
        $this->assertStringStartsWith('RCT-', $invoice->receipt_no);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_archived_plan_cannot_be_selected(): void
    {
        $owner = $this->newOwner();
        $this->plan('Business')->update(['status' => 'archived']);

        try {
            $this->service()->subscribe($owner, $this->plan('Business')->id);
            $this->fail('Expected ValidationException for an archived plan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('plan_id', $e->errors());
        }
    }

    public function test_admin_can_create_update_and_remove_plans(): void
    {
        $admin = User::where('username', 'admin')->first();

        $this->actingAs($admin)
            ->post('/admin/subscriptions/plans', [
                'name' => 'Enterprise',
                'price' => 120.00,
                'billing_cycle' => 'annual',
                'listing_limit' => 200,
                'featured_slots' => 20,
                'support_tier' => 'dedicated',
                'analytics_enabled' => true,
            ])
            ->assertRedirect(route('admin.subscriptions.plans.index'));

        $plan = SubscriptionPlan::where('name', 'Enterprise')->firstOrFail();
        $this->assertSame(200, $plan->listing_limit);
        $this->assertSame('120.00', $plan->price);

        $this->actingAs($admin)
            ->patch('/admin/subscriptions/plans/'.$plan->id, [
                'name' => 'Enterprise Plus',
                'price' => 150.00,
                'billing_cycle' => 'annual',
                'listing_limit' => 300,
                'featured_slots' => 20,
                'support_tier' => 'dedicated',
                'analytics_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertSame('Enterprise Plus', $plan->fresh()->name);
        $this->assertSame(300, $plan->fresh()->listing_limit);

        $this->actingAs($admin)
            ->delete('/admin/subscriptions/plans/'.$plan->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    public function test_plan_in_use_is_archived_not_deleted(): void
    {
        $admin = User::where('username', 'admin')->first();
        $inUse = $this->plan('Business');

        $this->actingAs($admin)
            ->delete('/admin/subscriptions/plans/'.$inUse->id)
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_plans', ['id' => $inUse->id, 'status' => 'archived']);
    }

    public function test_active_lease_generates_full_term_schedule_and_invoices(): void
    {
        $lease = $this->activeLease(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31')
        );

        $this->rentService()->generateFor($lease);

        $schedule = RentSchedule::where('lease_id', $lease->id)->firstOrFail();
        $this->assertSame('850.00', $schedule->rent_amount);
        $this->assertSame('2026-01-01', $schedule->start_date->toDateString());
        $this->assertSame('2026-12-31', $schedule->end_date->toDateString());

        $invoices = RentInvoice::where('schedule_id', $schedule->id)->orderBy('period_start')->get();
        $this->assertCount(12, $invoices);
        $this->assertSame(12, RentInvoice::where('schedule_id', $schedule->id)->distinct('invoice_no')->count());

        foreach ($invoices as $invoice) {
            $this->assertSame('850.00', $invoice->amount);
            $this->assertSame('draft', $invoice->status);
            $this->assertSame('RNT-2026-', substr($invoice->invoice_no, 0, 9));
        }

        $this->assertSame('2026-01-01', $invoices->first()->period_start->toDateString());
        $this->assertSame('2026-01-31', $invoices->first()->period_end->toDateString());
        $this->assertSame('2026-12-01', $invoices->last()->period_start->toDateString());
        $this->assertSame('2026-12-31', $invoices->last()->period_end->toDateString());
    }

    public function test_invoice_periods_cover_partial_start_and_end_months_exactly(): void
    {
        $lease = $this->activeLease(
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-03-10')
        );

        $this->rentService()->generateFor($lease);

        $invoices = RentInvoice::where('lease_id', $lease->id)->orderBy('period_start')->get();
        $this->assertCount(3, $invoices);

        $expected = [
            ['2026-01-15', '2026-01-31'],
            ['2026-02-01', '2026-02-28'],
            ['2026-03-01', '2026-03-10'],
        ];

        foreach ($invoices as $i => $invoice) {
            $this->assertSame($expected[$i][0], $invoice->period_start->toDateString(), 'period start #'.$i);
            $this->assertSame($expected[$i][1], $invoice->period_end->toDateString(), 'period end #'.$i);
        }

        for ($i = 1; $i < $invoices->count(); $i++) {
            $this->assertSame(
                $invoices[$i - 1]->period_end->copy()->addDay()->toDateString(),
                $invoices[$i]->period_start->toDateString(),
                'periods must be contiguous'
            );
        }
    }

    public function test_generation_is_idempotent_and_inactive_leases_generate_nothing(): void
    {
        $lease = $this->activeLease();

        $this->rentService()->generateFor($lease);
        $this->rentService()->generateFor($lease);
        $this->rentService()->generateFor($lease);

        $this->assertSame(1, RentSchedule::where('lease_id', $lease->id)->count());

        $draft = Lease::create([
            'property_id' => $this->makeAvailableProperty($this->owner())->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-DRAFT-'.$lease->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);

        $this->rentService()->generateFor($draft);

        $this->assertSame(0, RentSchedule::where('lease_id', $draft->id)->count());
        $this->assertSame(0, RentInvoice::where('lease_id', $draft->id)->count());
    }

    public function test_lifecycle_moves_invoices_to_due_then_overdue_with_one_reminder(): void
    {
        Notification::fake();

        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);

        $this->assertSame(3, RentInvoice::where('lease_id', $lease->id)->where('status', 'draft')->count());

        $this->rentService()->runInvoiceLifecycle();

        $this->assertSame(1, RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->count());
        $this->assertSame(2, RentInvoice::where('lease_id', $lease->id)->where('status', 'draft')->count());
        $this->assertSame(0, RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->count());

        $due = RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail();
        $this->assertNotNull($due->reminded_at);

        Notification::assertSentToTimes($this->tenant(), RentInvoiceDueNotification::class, 1);

        $this->travel(60)->days();
        $this->rentService()->runInvoiceLifecycle();

        $this->assertSame(2, RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->count());
        $this->assertSame(1, RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->count());
        $this->assertSame(0, RentInvoice::where('lease_id', $lease->id)->where('status', 'draft')->count());
        Notification::assertSentToTimes($this->tenant(), RentInvoiceDueNotification::class, 1);
    }

    public function test_signing_a_lease_generates_its_rent_schedule(): void
    {
        $owner = $this->owner();
        $property = $this->makeAvailableProperty($owner);
        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-SIGN-'.strtoupper(Str::random(6)),
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 850.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'draft',
            'clause_version' => 1,
        ]);
        $property->update(['status' => 'reserved']);

        $leases = app(LeaseService::class);
        $leases->sendForSignature($owner, $lease->id);
        $leases->sign($this->tenant(), $lease->id, 'tenant-token');
        $leases->sign($owner, $lease->id, 'owner-token');

        $this->assertSame('active', $lease->fresh()->status);
        $this->assertSame(1, RentSchedule::where('lease_id', $lease->id)->count());
        $this->assertTrue(RentInvoice::where('lease_id', $lease->id)->count() > 0);
        $this->assertSame('850.00', RentInvoice::where('lease_id', $lease->id)->first()->amount);
    }

    public function test_rent_queries_are_scoped_to_the_parties(): void
    {
        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);

        $otherTenant = User::factory()->create(['name' => 'Other Tenant', 'email' => 'other-tenant@example.test', 'password_changed_at' => now()]);
        $otherTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);
        $otherLease = $this->activeLease();
        $otherLease->update(['lease_no' => 'LSE-TEST-OTHER', 'tenant_id' => $otherTenant->id]);
        $this->rentService()->generateFor($otherLease);

        $tenantInvoices = $this->rentService()->invoicesForTenant($this->tenant());
        $this->assertTrue($tenantInvoices->every(fn ($invoice) => $invoice->tenant_id === $this->tenant()->id));

        $ownerSchedules = $this->rentService()->schedulesForOwner($this->owner());
        $this->assertTrue($ownerSchedules->every(fn ($schedule) => $schedule->lease->property->owner_id === $this->owner()->id));
        $this->assertTrue($ownerSchedules->pluck('id')->contains($lease->rentSchedule->id));
        $this->assertTrue($ownerSchedules->pluck('id')->contains($otherLease->rentSchedule->id));

        $otherInvoices = $this->rentService()->invoicesForTenant($otherTenant);
        $this->assertTrue($otherInvoices->every(fn ($invoice) => $invoice->tenant_id === $otherTenant->id));
    }

    // ---------- Wave 4 slice 4: rent payments (FR-03/FR-04) ----------

    private function paymentService(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function updateConfig(string $key, string $value): void
    {
        SystemConfiguration::where('key', $key)->update(['value' => $value]);
        Cache::forget('dzimba.config.'.$key);
    }

    private function dueInvoice(Lease $lease): RentInvoice
    {
        return RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail();
    }

    public function test_exact_cash_payment_settles_invoice_immediately_and_emits_receipt_notification(): void
    {
        Notification::fake();

        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $payment = $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
            'reference' => 'Cash at branch — test',
        ]);

        $this->assertSame('settled', $payment->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertMatchesRegularExpression('/^RCT-[A-Z0-9]{8}$/', $payment->receipt_no);
        $this->assertNotNull($payment->paid_at);

        Notification::assertSentToTimes($this->tenant(), ReceiptIssuedNotification::class, 1);
    }

    public function test_payment_must_match_the_invoice_amount_exactly(): void
    {
        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $this->expectException(ValidationException::class);
        $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '800.00',
            'method' => 'cash',
        ]);
    }

    public function test_a_paid_invoice_cannot_be_paid_twice(): void
    {
        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
        ]);

        $this->expectException(ValidationException::class);
        $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
        ]);
    }

    public function test_high_value_payment_waits_for_staff_then_approval_settles_it(): void
    {
        Notification::fake();

        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $payment = $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
            'reference' => 'At branch',
        ]);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('due', $invoice->fresh()->status);
        $this->assertNull($payment->receipt_no);

        $this->paymentService()->approve($this->admin(), $payment);

        $settled = $payment->fresh();
        $this->assertSame('settled', $settled->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($settled->receipt_no);
        $this->assertSame($this->admin()->id, $settled->received_by);

        Notification::assertSentToTimes($this->tenant(), ReceiptIssuedNotification::class, 1);
    }

    public function test_rejected_payment_leaves_invoice_owing_and_allows_re_payment(): void
    {
        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $payment = $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
        ]);

        $this->paymentService()->reject($this->admin(), $payment);

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('due', $invoice->fresh()->status);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $repayment = $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'cash',
        ]);

        $this->assertSame('settled', $repayment->status);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_bank_payment_requires_proof_of_payment_when_configured(): void
    {
        $lease = $this->activeLease();
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = $this->dueInvoice($lease);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $this->expectException(ValidationException::class);
        $this->paymentService()->recordPayment($this->tenant(), $invoice, [
            'amount' => '850.00',
            'method' => 'bank',
            'reference' => 'BRN-221',
            'pop_path' => null,
        ]);
    }

    public function test_receipt_numbers_are_unique_across_payments(): void
    {
        $this->updateConfig('payments.approval.threshold', '2000.00');

        $receiptNos = [];

        for ($i = 0; $i < 2; $i++) {
            $owner = User::factory()->create(['name' => 'Receipt Owner '.$i, 'email' => 'receipt-owner-'.$i.'@example.test', 'password_changed_at' => now()]);
            $owner->roles()->attach(Role::where('name', 'Owner')->first()->id);

            $property = Property::create([
                'owner_id' => $owner->id,
                'title' => 'Receipt Test '.$i,
                'property_type' => 'flat',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'price' => 500.00,
                'status' => 'available',
                'suburb' => 'Test Suburb',
                'city' => 'Harare',
            ]);

            $lease = Lease::create([
                'property_id' => $property->id,
                'tenant_id' => $this->tenant()->id,
                'application_id' => null,
                'lease_no' => 'LSE-TEST-RCT-'.$i.strtoupper(Str::random(4)),
                'start_date' => now(),
                'end_date' => now()->addMonths(2)->endOfMonth(),
                'rent_amount' => 500.00,
                'deposit_amount' => 0,
                'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
                'status' => 'active',
                'clause_version' => 1,
            ]);

            $this->rentService()->generateFor($lease);
            $this->rentService()->runInvoiceLifecycle();

            $payment = $this->paymentService()->recordPayment($this->tenant(), $this->dueInvoice($lease), [
                'amount' => '500.00',
                'method' => 'cash',
            ]);

            $receiptNos[] = $payment->receipt_no;
        }

        $this->assertCount(2, array_unique($receiptNos));
        $this->assertMatchesRegularExpression('/^RCT-[A-Z0-9]{8}$/', $receiptNos[0]);
    }

    public function test_tenant_cannot_pay_another_tenants_invoice(): void
    {
        $otherTenant = User::factory()->create(['name' => 'Other Tenant', 'email' => 'other-payer@example.test', 'password_changed_at' => now()]);
        $otherTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $lease = $this->activeLease();
        $lease->update(['tenant_id' => $otherTenant->id, 'lease_no' => 'LSE-TEST-OTHER-2']);
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $invoice = RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail();

        $this->actingAs($this->tenant())
            ->post('/tenant/rent/'.$invoice->id.'/pay', [
                'amount' => '850.00',
                'method' => 'cash',
            ])
            ->assertNotFound();
    }

    public function test_settling_an_invoice_removes_it_from_arrears_totals(): void
    {
        $testTenant = User::factory()->create(['name' => 'Rent Paying Tenant', 'email' => 'rent-payer@example.test', 'password_changed_at' => now()]);
        $testTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $property = $this->makeAvailableProperty($this->newOwner());
        $property->update(['price' => 500.00]);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $testTenant->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-ARREARS-'.strtoupper(Str::random(6)),
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();

        $this->travel(60)->days();
        $this->rentService()->runInvoiceLifecycle();

        $invoices = RentInvoice::where('lease_id', $lease->id)->get();
        $this->assertSame(2, $invoices->where('status', 'overdue')->count());
        $this->assertSame(1, $invoices->where('status', 'due')->count());

        $before = $this->rentService()->tenantSummary($testTenant);
        $this->assertEquals(1000.0, $before['overdue']);
        $this->assertEquals(500.0, $before['due']);

        $this->updateConfig('payments.approval.threshold', '2000.00');

        $this->paymentService()->recordPayment($testTenant, $invoices->where('status', 'overdue')->first(), [
            'amount' => '500.00',
            'method' => 'cash',
        ]);

        $after = $this->rentService()->tenantSummary($testTenant);
        $this->assertEquals(500.0, $after['overdue']);
        $this->assertEquals(500.0, $after['due']);
        $this->assertEquals(500.0, $after['paid']);
    }

    // ---------- Wave 4 slice 5: arrears, late fees & income (FR-06/FR-07) ----------

    private function overdueLease(): Lease
    {
        $lease = $this->activeLease(now()->subMonths(2)->startOfDay(), now()->addMonths(2)->endOfMonth());
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();

        return $lease;
    }

    private function financial(): FinancialSummaryService
    {
        return app(FinancialSummaryService::class);
    }

    private function enableLateFees(string $type = 'percent', string $value = '1.00', int $periodDays = 30, string $cap = '0.00'): void
    {
        $this->updateConfig('late_fees.enabled', '1');
        $this->updateConfig('late_fees.type', $type);
        $this->updateConfig('late_fees.value', $value);
        $this->updateConfig('late_fees.period_days', (string) $periodDays);
        $this->updateConfig('late_fees.cap', $cap);
    }

    public function test_late_fees_accrue_from_percent_config_per_applied_period_and_are_idempotent(): void
    {
        $this->enableLateFees('percent', '1.00', 30, '0.00');
        $lease = $this->overdueLease();

        $invoice = RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->firstOrFail();
        $daysOverdue = $invoice->period_end->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay());
        $periods = intdiv($daysOverdue, 30) + 1;
        $expected = sprintf('%01.2f', ((float) $invoice->amount) * 0.01 * $periods);

        $this->rentService()->accrueLateFees();
        $this->assertSame($expected, $invoice->fresh()->late_fee);

        $this->rentService()->accrueLateFees();
        $this->assertSame($expected, $invoice->fresh()->late_fee, 'Recomputing must never change the stored figure.');
    }

    public function test_percent_late_fees_respect_the_configured_cap(): void
    {
        $this->enableLateFees('percent', '1.00', 30, '10.00');
        $lease = $this->overdueLease();

        $invoice = RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->firstOrFail();

        $this->rentService()->accrueLateFees();
        $this->assertSame('10.00', $invoice->fresh()->late_fee);
    }

    public function test_fixed_late_fee_applies_a_flat_charge_per_period(): void
    {
        $this->enableLateFees('fixed', '50.00', 30, '0.00');
        $lease = $this->overdueLease();

        $invoice = RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->firstOrFail();
        $daysOverdue = $invoice->period_end->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay());

        $this->rentService()->accrueLateFees();
        $this->assertSame(sprintf('%01.2f', (intdiv($daysOverdue, 30) + 1) * 50.00), $invoice->fresh()->late_fee);
    }

    public function test_late_fees_do_not_accrue_while_disabled(): void
    {
        $lease = $this->overdueLease();
        $this->rentService()->accrueLateFees();

        $overdue = RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->get();
        $this->assertGreaterThan(0, $overdue->count());
        $this->assertTrue($overdue->every(fn (RentInvoice $invoice) => (float) $invoice->late_fee === 0.0));
    }

    public function test_falling_overdue_notifies_the_tenant_and_the_property_owner_once(): void
    {
        Notification::fake();

        $lease = $this->activeLease(now()->subMonths(2)->startOfDay(), now()->addMonths(2)->endOfMonth());
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();

        $overdueCount = RentInvoice::where('lease_id', $lease->id)->where('status', 'overdue')->count();
        $this->assertGreaterThan(0, $overdueCount);

        Notification::assertSentTo($this->tenant(), RentInvoiceOverdueNotification::class, $overdueCount);
        Notification::assertSentTo($this->owner(), RentInvoiceOverdueNotification::class, $overdueCount);

        $this->rentService()->runInvoiceLifecycle();
        Notification::assertSentTo($this->tenant(), RentInvoiceOverdueNotification::class, $overdueCount);
    }

    public function test_tenant_arrear_statement_matches_the_ledger_exactly(): void
    {
        $this->enableLateFees('percent', '1.00', 30, '0.00');

        $testTenant = User::factory()->create(['name' => 'Statement Tenant', 'email' => 'statement-tenant@example.test', 'password_changed_at' => now()]);
        $testTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $property = $this->makeAvailableProperty($this->newOwner());
        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $testTenant->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-STATEMENT-'.strtoupper(Str::random(6)),
            'start_date' => now()->subMonths(2)->startOfDay(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => $property->price,
            'deposit_amount' => $property->deposit ?? 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $this->rentService()->accrueLateFees();

        $unpaid = RentInvoice::where('lease_id', $lease->id)->whereIn('status', ['due', 'overdue'])->get();
        $statement = $this->rentService()->tenantStatement($testTenant);

        $this->assertCount($unpaid->count(), $statement['rows']);

        $expectedAmount = $unpaid->sum('amount');
        $expectedLateFees = $unpaid->where('status', 'overdue')->sum('late_fee');

        $this->assertSame(sprintf('%01.2f', $expectedAmount + $expectedLateFees), $statement['outstanding_total']);
        $this->assertSame(sprintf('%01.2f', $expectedLateFees), $statement['late_fees_total']);
        $this->assertSame(
            sprintf('%01.2f', $unpaid->where('status', 'overdue')->sum('amount') + $expectedLateFees),
            $statement['arrears_total']
        );
    }

    public function test_owner_arrear_statement_only_includes_own_properties(): void
    {
        $owner = $this->newOwner();
        $ownerLease = Lease::create([
            'property_id' => $this->makeAvailableProperty($owner)->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-OWNER-'.strtoupper(Str::random(6)),
            'start_date' => now()->subMonths(2)->startOfDay(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
        $this->rentService()->generateFor($ownerLease);
        $this->rentService()->runInvoiceLifecycle();

        $stranger = User::factory()->create(['name' => 'Stranger Owner', 'email' => 'stranger-owner@example.test', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Owner')->first()->id);
        $strangerLease = Lease::create([
            'property_id' => $this->makeAvailableProperty($stranger)->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-STRANGER-'.strtoupper(Str::random(6)),
            'start_date' => now()->subMonths(2)->startOfDay(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
        $this->rentService()->generateFor($strangerLease);
        $this->rentService()->runInvoiceLifecycle();

        $statement = $this->rentService()->ownerStatement($owner);
        $this->assertNotEmpty($statement['rows']);
        foreach ($statement['rows'] as $row) {
            $this->assertSame($ownerLease->lease_no, $row['lease_no']);
        }

        foreach ($this->rentService()->ownerStatement($stranger)['rows'] as $row) {
            $this->assertSame($strangerLease->lease_no, $row['lease_no']);
        }
    }

    public function test_owner_income_aggregation_totals_settled_payments_for_the_month(): void
    {
        $testTenant = User::factory()->create(['name' => 'Income Tenant', 'email' => 'income-tenant@example.test', 'password_changed_at' => now()]);
        $testTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);
        $owner = $this->newOwner();

        $property = $this->makeAvailableProperty($owner);
        $property->update(['price' => 500.00]);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $testTenant->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-INCOME-'.strtoupper(Str::random(6)),
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);
        $this->rentService()->generateFor($lease);
        $this->rentService()->runInvoiceLifecycle();
        $this->updateConfig('payments.approval.threshold', '2000.00');

        $this->paymentService()->recordPayment($testTenant, $this->dueInvoice($lease), [
            'amount' => '500.00',
            'method' => 'cash',
        ]);

        $snapshot = $this->financial()->ownerIndex($owner);

        $this->assertSame('500.00', $snapshot['monthly_income']);
        $this->assertSame('0.00', $snapshot['rent_due']);
        $this->assertCount(6, $snapshot['income_trend']);
        $this->assertSame(Carbon::now()->format('Y-m'), $snapshot['income_trend'][5]['month']);
        $this->assertSame('500.00', $snapshot['income_trend'][5]['income']);
    }

    public function test_occupancy_rate_is_computed_from_owned_property_statuses(): void
    {
        $owner = $this->newOwner();
        $this->makeAvailableProperty($owner);
        $occupied = $this->makeAvailableProperty($owner);
        $occupied->update(['status' => 'occupied']);

        $snapshot = $this->financial()->ownerIndex($owner);

        $this->assertSame(2, $snapshot['total_properties']);
        $this->assertSame(1, $snapshot['occupied_properties']);
        $this->assertSame(50.0, $snapshot['occupancy_rate']);
    }

    public function test_owner_dashboard_exposes_the_ledger_based_financial_snapshot(): void
    {
        $owner = $this->owner();
        $this->makeAvailableProperty($owner);

        $this->actingAs($owner)
            ->get('/owner')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Dashboard')
                ->where('stats.monthly_income', '0.00')
                ->has('stats.occupancy_rate')
                ->has('financial.monthly_income')
                ->has('financial.outstanding_total')
                ->has('financial.income_trend'));
    }
}