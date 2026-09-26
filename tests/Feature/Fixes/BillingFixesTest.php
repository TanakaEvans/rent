<?php

namespace Tests\Feature\Fixes;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentInvoice;
use App\Models\RentSchedule;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\ReceiptIssuedNotification;
use App\Notifications\RentPaymentReceivedNotification;
use App\Services\PaymentService;
use App\Services\RentService;
use App\Services\SubscriptionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed();
    }

    // ---------- helpers ----------

    private function owner(): User
    {
        return User::where('email', 'owner@dzimba.local')->firstOrFail();
    }

    private function tenant(): User
    {
        return User::where('email', 'tenant@dzimba.local')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@system.local')->firstOrFail();
    }

    private function rent(): RentService
    {
        return app(RentService::class);
    }

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function subscriptions(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function updateConfig(string $key, string $value): void
    {
        SystemConfiguration::where('key', $key)->update(['value' => $value]);
        Cache::forget('dzimba.config.'.$key);
    }

    private function property(string $terms = 'monthly', string $price = '850.00', string $currency = 'USD'): Property
    {
        return Property::create([
            'owner_id' => $this->owner()->id,
            'title' => 'Billing Fix House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => $price,
            'currency' => $currency,
            'payment_terms' => $terms,
            'status' => 'available',
            'suburb' => 'Avondale',
            'city' => 'Harare',
        ]);
    }

    private function lease(Property $property, Carbon $start, Carbon $end, ?array $terms = ['frequency' => 'monthly', 'due_day' => 1]): Lease
    {
        return Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'application_id' => null,
            'lease_no' => 'LSE-FIX-'.strtoupper(Str::random(6)),
            'start_date' => $start,
            'end_date' => $end,
            'rent_amount' => $property->price,
            'deposit_amount' => 0,
            'payment_terms' => $terms,
            'status' => 'active',
            'clause_version' => 1,
        ]);
    }

    /**
     * An active monthly lease that started today, with its first invoice due.
     */
    private function dueInvoice(string $price = '850.00'): RentInvoice
    {
        $lease = $this->lease($this->property('monthly', $price), now()->startOfDay(), now()->addMonths(2)->endOfMonth());
        $this->rent()->generateFor($lease);

        return RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail();
    }

    private function pendingBankPayment(RentInvoice $invoice): Payment
    {
        $this->actingAs($this->tenant())
            ->post(route('tenant.rent.pay', $invoice), [
                'amount' => $invoice->payableAmount(),
                'method' => 'bank',
                'reference' => 'BANK-REF-1',
                'pop' => UploadedFile::fake()->create('pop.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('tenant.rent.index'));

        return Payment::where('invoice_id', $invoice->id)->where('status', 'pending')->firstOrFail();
    }

    private function newOwner(): User
    {
        $user = User::factory()->create(['email' => 'billing-owner@example.test', 'status' => 'active', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail()->id);

        return $user;
    }

    // ---------- 1. rent:process scheduled + immediate lifecycle ----------

    public function test_rent_process_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'rent:process'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    public function test_activating_a_lease_makes_invoices_already_due_payable_immediately(): void
    {
        $lease = $this->lease($this->property(), now()->startOfDay(), now()->addMonths(2)->endOfMonth());

        $this->rent()->generateFor($lease);

        $this->assertSame(1, RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->count());
        $this->assertSame(2, RentInvoice::where('lease_id', $lease->id)->where('status', 'draft')->count());

        $this->rent()->generateFor($lease);
        $this->assertSame(3, RentInvoice::where('lease_id', $lease->id)->count());
    }

    // ---------- 2. payment terms ----------

    public function test_quarterly_lease_produces_one_invoice_per_quarter_at_the_quarterly_amount(): void
    {
        $property = $this->property('quarterly', '2400.00');
        $lease = $this->lease($property, Carbon::parse('2027-01-01'), Carbon::parse('2027-12-31'), ['frequency' => 'quarterly', 'due_day' => 1]);

        $this->rent()->generateFor($lease);

        $schedule = RentSchedule::where('lease_id', $lease->id)->firstOrFail();
        $this->assertSame('quarterly', $schedule->payment_terms['frequency']);

        $invoices = RentInvoice::where('lease_id', $lease->id)->orderBy('period_start')->get();
        $this->assertCount(4, $invoices);

        $expected = [
            ['2027-01-01', '2027-03-31'],
            ['2027-04-01', '2027-06-30'],
            ['2027-07-01', '2027-09-30'],
            ['2027-10-01', '2027-12-31'],
        ];
        foreach ($invoices as $i => $invoice) {
            $this->assertSame($expected[$i][0], $invoice->period_start->toDateString());
            $this->assertSame($expected[$i][1], $invoice->period_end->toDateString());
            $this->assertSame('2400.00', $invoice->amount);
        }
    }

    public function test_property_payment_terms_are_the_fallback_and_long_terms_anchor_on_the_lease_start(): void
    {
        $property = $this->property('yearly', '9000.00');
        $lease = $this->lease($property, Carbon::parse('2027-03-15'), Carbon::parse('2028-03-14'), null);

        $this->rent()->generateFor($lease);

        $invoices = RentInvoice::where('lease_id', $lease->id)->get();
        $this->assertCount(1, $invoices);
        $this->assertSame('2027-03-15', $invoices->first()->period_start->toDateString());
        $this->assertSame('2028-03-14', $invoices->first()->period_end->toDateString());
        $this->assertSame('9000.00', $invoices->first()->amount);
    }

    public function test_mid_month_quarterly_lease_periods_are_contiguous_and_clamped(): void
    {
        $property = $this->property('quarterly', '1500.00');
        $lease = $this->lease($property, Carbon::parse('2027-01-15'), Carbon::parse('2027-08-31'), ['frequency' => 'quarterly']);

        $this->rent()->generateFor($lease);

        $periods = RentInvoice::where('lease_id', $lease->id)->orderBy('period_start')->get()
            ->map(fn (RentInvoice $i) => [$i->period_start->toDateString(), $i->period_end->toDateString()])
            ->all();

        $this->assertSame([
            ['2027-01-15', '2027-04-14'],
            ['2027-04-15', '2027-07-14'],
            ['2027-07-15', '2027-08-31'],
        ], $periods);
    }

    // ---------- 3. late fees payable ----------

    public function test_payment_of_rent_plus_late_fee_settles_the_invoice(): void
    {
        $this->updateConfig('payments.approval.threshold', '5000.00');
        $invoice = $this->dueInvoice();
        $invoice->update(['status' => 'overdue', 'late_fee' => '8.50']);

        $this->assertSame('858.50', $invoice->fresh()->payableAmount());

        try {
            $this->payments()->recordPayment($this->tenant(), $invoice->fresh(), ['amount' => '850.00', 'method' => 'cash']);
            $this->fail('Paying the rent without the late fee must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $payment = $this->payments()->recordPayment($this->tenant(), $invoice->fresh(), ['amount' => '858.50', 'method' => 'cash']);

        $this->assertSame('settled', $payment->status);
        $this->assertSame('858.50', $payment->amount);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_tenant_rent_page_exposes_the_payable_total(): void
    {
        $invoice = $this->dueInvoice();
        $invoice->update(['status' => 'overdue', 'late_fee' => '10.00']);

        $this->actingAs($this->tenant())
            ->get(route('tenant.rent.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Rent')
                ->where('invoices', fn ($invoices) => collect($invoices)->firstWhere('id', $invoice->id)['payable_amount'] === '860.00'));
    }

    // ---------- 5. POP file restrictions ----------

    public function test_proof_of_payment_must_be_an_image_or_pdf_and_is_stored_privately(): void
    {
        $invoice = $this->dueInvoice();

        $this->actingAs($this->tenant())
            ->post(route('tenant.rent.pay', $invoice), [
                'amount' => $invoice->payableAmount(),
                'method' => 'bank',
                'pop' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('pop');

        $this->actingAs($this->tenant())
            ->post(route('tenant.rent.pay', $invoice), [
                'amount' => $invoice->payableAmount(),
                'method' => 'bank',
                'pop' => UploadedFile::fake()->create('huge.pdf', 5000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('pop');

        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());

        $payment = $this->pendingBankPayment($invoice);

        Storage::disk('local')->assertExists($payment->pop_path);
        Storage::disk('public')->assertMissing($payment->pop_path);
    }

    // ---------- 6. admin POP + receipt routes, owner visibility ----------

    public function test_admin_can_view_proof_of_payment_and_download_receipt(): void
    {
        $payment = $this->pendingBankPayment($this->dueInvoice());

        $this->actingAs($this->admin())
            ->get(route('admin.rent.payments.pop', $payment))
            ->assertOk()
            ->assertDownload('proof-of-payment-'.$payment->id.'.pdf');

        $this->actingAs($this->admin())
            ->get(route('admin.rent.payments.receipt', $payment))
            ->assertNotFound();

        $this->payments()->approve($this->admin(), $payment);

        $response = $this->actingAs($this->admin())->get(route('admin.rent.payments.receipt', $payment));
        $response->assertOk();
        $this->assertStringContainsString($payment->fresh()->receipt_no, $response->streamedContent());
    }

    public function test_tenant_and_owner_cannot_use_admin_payment_routes(): void
    {
        $payment = $this->pendingBankPayment($this->dueInvoice());
        $this->payments()->approve($this->admin(), $payment);

        foreach ([$this->tenant(), $this->owner()] as $user) {
            $this->actingAs($user)->get(route('admin.rent.payments.pop', $payment))->assertForbidden();
            $this->actingAs($user)->get(route('admin.rent.payments.receipt', $payment))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('admin.rent.payments.pop', $payment))->assertRedirect(route('login'));
    }

    public function test_tenant_receipt_is_only_available_once_settled(): void
    {
        $payment = $this->pendingBankPayment($this->dueInvoice());

        $this->actingAs($this->tenant())
            ->get(route('tenant.rent.receipt', $payment))
            ->assertNotFound();
    }

    public function test_owner_rent_page_lists_payments_on_their_properties(): void
    {
        $payment = $this->pendingBankPayment($this->dueInvoice());
        $this->payments()->approve($this->admin(), $payment);
        $receiptNo = $payment->fresh()->receipt_no;

        $this->actingAs($this->owner())
            ->get(route('owner.rent.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Rent/Index')
                ->where('schedules', function ($schedules) use ($receiptNo) {
                    $payments = collect($schedules)->flatMap(fn ($s) => collect($s['invoices'])->flatMap(fn ($i) => $i['payments']));
                    $row = $payments->firstWhere('receipt_no', $receiptNo);

                    return $row !== null
                        && $row['method'] === 'bank'
                        && $row['reference'] === 'BANK-REF-1'
                        && $row['status'] === 'settled';
                }));
    }

    // ---------- 7. notifications ----------

    public function test_rejecting_a_payment_notifies_the_tenant_with_the_note(): void
    {
        $payment = $this->pendingBankPayment($this->dueInvoice());
        Notification::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.rent.payments.reject', $payment), ['note' => 'Reference not found on statement'])
            ->assertRedirect(route('admin.rent.payments.index'));

        $this->assertSame('rejected', $payment->fresh()->status);
        Notification::assertSentTo(
            $this->tenant(),
            PaymentRejectedNotification::class,
            fn (PaymentRejectedNotification $n) => $n->note === 'Reference not found on statement'
                && str_contains($n->toDatabase($this->tenant())['body'], 'Reference not found on statement')
        );
    }

    public function test_settling_a_payment_notifies_the_property_owner(): void
    {
        Notification::fake();
        $this->updateConfig('payments.approval.threshold', '5000.00');
        $invoice = $this->dueInvoice();

        $payment = $this->payments()->recordPayment($this->tenant(), $invoice, ['amount' => '850.00', 'method' => 'cash']);

        Notification::assertSentTo($this->tenant(), ReceiptIssuedNotification::class);
        Notification::assertSentTo(
            $this->owner(),
            RentPaymentReceivedNotification::class,
            fn (RentPaymentReceivedNotification $n) => $n->payment->is($payment)
                && $n->toDatabase($this->owner())['title'] === 'Rent payment received'
        );
    }

    // ---------- 8. documents ----------

    public function test_document_lists_include_the_property_title_and_location(): void
    {
        $lease = $this->lease($this->property(), now()->startOfDay(), now()->addYear());
        Document::create([
            'lease_id' => $lease->id,
            'type' => 'lease_agreement',
            'name' => 'Lease Agreement '.$lease->lease_no,
            'content' => 'Agreement',
            'mime' => 'text/plain',
            'size' => 9,
            'version' => 1,
            'visibility' => 'private',
        ]);

        $assert = fn ($page) => $page->where('documents', function ($documents) use ($lease) {
            $doc = collect($documents)->first(fn ($d) => $d['lease']['lease_no'] === $lease->lease_no);

            return $doc !== null
                && $doc['lease']['property']['title'] === 'Billing Fix House'
                && $doc['lease']['property']['city'] === 'Harare';
        });

        $this->actingAs($this->tenant())->get(route('tenant.documents.index'))->assertInertia($assert);
        $this->actingAs($this->owner())->get(route('owner.documents.index'))->assertInertia($assert);
    }

    // ---------- 10. subscriptions ----------

    public function test_renewing_the_current_plan_in_grace_reactivates_it_with_a_full_invoice(): void
    {
        $owner = $this->newOwner();
        $basic = SubscriptionPlan::where('name', 'Basic')->firstOrFail();
        $subscription = $this->subscriptions()->subscribe($owner, $basic->id);
        $subscription->update(['ends_at' => now()->subDay()]);
        $this->subscriptions()->runExpiryCheck();
        $this->assertSame('grace', $subscription->fresh()->status);
        $invoicesBefore = $subscription->invoices()->count();

        $this->actingAs($owner)
            ->post(route('owner.subscriptions.subscribe'), ['plan_id' => $basic->id])
            ->assertRedirect(route('owner.subscriptions.index'))
            ->assertSessionHasNoErrors();

        $renewed = $subscription->fresh();
        $this->assertSame('active', $renewed->status);
        $this->assertTrue($renewed->ends_at->isFuture());
        $this->assertNull($renewed->graceUntil());
        $this->assertSame($invoicesBefore + 1, $renewed->invoices()->count());
        $this->assertSame(sprintf('%01.2f', (float) $basic->price), $renewed->invoices()->latest('id')->first()->amount);
        $this->assertTrue($renewed->history()->where('event', 'renewed')->exists());
    }

    public function test_equal_price_switch_in_grace_reactivates_the_subscription(): void
    {
        $owner = $this->newOwner();
        $basic = SubscriptionPlan::where('name', 'Basic')->firstOrFail();
        $twin = SubscriptionPlan::create([
            'name' => 'Basic Twin',
            'price' => $basic->price,
            'billing_cycle' => 'monthly',
            'listing_limit' => 3,
            'featured_slots' => 0,
            'support_tier' => 'standard',
            'status' => 'active',
        ]);
        $subscription = $this->subscriptions()->subscribe($owner, $basic->id);
        $subscription->update(['ends_at' => now()->subDay()]);
        $this->subscriptions()->runExpiryCheck();

        $switched = $this->subscriptions()->subscribe($owner, $twin->id);

        $this->assertSame('active', $switched->status);
        $this->assertSame($twin->id, $switched->plan_id);
        $this->assertTrue($switched->ends_at->isFuture());
    }

    public function test_renewing_a_suspended_current_plan_starts_an_active_subscription(): void
    {
        $owner = $this->newOwner();
        $basic = SubscriptionPlan::where('name', 'Basic')->firstOrFail();
        $subscription = $this->subscriptions()->subscribe($owner, $basic->id);
        $subscription->update(['ends_at' => now()->subDays(30)]);
        $this->subscriptions()->runExpiryCheck();
        $this->assertSame('suspended', $subscription->fresh()->status);

        $renewed = $this->subscriptions()->subscribe($owner, $basic->id);

        $this->assertSame('active', $renewed->status);
        $this->assertSame($basic->id, $renewed->plan_id);
        $this->assertSame('active', $owner->currentSubscription()->status);
    }

    public function test_reselecting_the_active_plan_is_refused_unless_it_cancels_a_pending_change(): void
    {
        $owner = $this->newOwner();
        $professional = SubscriptionPlan::where('name', 'Professional')->firstOrFail();
        $basic = SubscriptionPlan::where('name', 'Basic')->firstOrFail();
        $this->subscriptions()->subscribe($owner, $professional->id);

        $this->actingAs($owner)
            ->post(route('owner.subscriptions.subscribe'), ['plan_id' => $professional->id])
            ->assertSessionHasErrors('plan_id');

        $pending = $this->subscriptions()->subscribe($owner, $basic->id);
        $this->assertSame($basic->id, $pending->pendingDowngradeTo());

        $this->actingAs($owner)
            ->get(route('owner.subscriptions.index'))
            ->assertInertia(fn ($page) => $page
                ->where('pending_change.plan_name', 'Basic')
                ->where('pending_change.type', 'downgrade'));

        $kept = $this->subscriptions()->subscribe($owner, $professional->id);
        $this->assertNull($kept->pendingDowngradeTo());
        $this->assertSame($professional->id, $kept->plan_id);
    }

    public function test_free_plan_never_lapses_into_grace_or_suspension(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->subscriptions()->ensureFor($owner);
        $this->assertSame('Free', $subscription->plan->name);

        $subscription->update(['ends_at' => now()->subDays(40)]);
        $this->subscriptions()->runExpiryCheck();

        $fresh = $subscription->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertTrue($fresh->ends_at->isFuture());
        $this->assertSame(0, $fresh->invoices()->count());

        $this->travel(90)->days();
        $this->subscriptions()->runExpiryCheck();
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_free_subscription_already_in_grace_is_auto_renewed(): void
    {
        $owner = $this->newOwner();
        $subscription = $this->subscriptions()->ensureFor($owner);
        $subscription->update([
            'status' => 'grace',
            'ends_at' => now()->subDays(10),
            'details' => ['grace_until' => now()->subDays(3)->toDateTimeString()],
        ]);

        $this->subscriptions()->runExpiryCheck();

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertNull($subscription->fresh()->graceUntil());
    }

    // ---------- 11/12. admin plans ----------

    public function test_plan_can_be_created_and_updated_without_a_listing_limit_key(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.subscriptions.plans.store'), [
                'name' => 'Unlimited Pro',
                'price' => '40.00',
                'billing_cycle' => 'monthly',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.subscriptions.plans.index'));

        $plan = SubscriptionPlan::where('name', 'Unlimited Pro')->firstOrFail();
        $this->assertNull($plan->listing_limit);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.plans.update', $plan), [
                'name' => 'Unlimited Pro',
                'price' => '45.00',
                'billing_cycle' => 'monthly',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('45.00', sprintf('%01.2f', (float) $plan->fresh()->price));
    }

    public function test_default_plan_cannot_be_archived_deleted_or_renamed(): void
    {
        $free = SubscriptionPlan::where('name', 'Free')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.subscriptions.plans.destroy', $free))
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseHas('subscription_plans', ['id' => $free->id, 'status' => 'active', 'name' => 'Free']);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.plans.update', $free), [
                'name' => 'Starter',
                'price' => '0',
                'billing_cycle' => 'monthly',
                'listing_limit' => 1,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('Free', $free->fresh()->name);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.plans.update', $free), [
                'name' => 'Free',
                'price' => '0',
                'billing_cycle' => 'monthly',
                'listing_limit' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $free->fresh()->listing_limit);

        $this->assertNotNull($this->subscriptions()->ensureFor($this->newOwner())->plan);
    }
}
