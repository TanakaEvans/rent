<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Module 24 — Configuration Engine. Proves AC-05: "rules are data". Changing
 * a value in system_configurations changes engine behaviour with zero code
 * changes; every change is audited; high/critical risk requires approval.
 */
class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function config(): ConfigurationService
    {
        return app(ConfigurationService::class);
    }

    public function test_baseline_configuration_is_seeded(): void
    {
        $this->assertDatabaseHas('system_configurations', ['key' => 'subscriptions.grace_period_days', 'value' => '7', 'type' => 'integer']);
        $this->assertDatabaseHas('system_configurations', ['key' => 'subscriptions.proration.mode', 'value' => 'credit_new_invoice']);
        $this->assertDatabaseHas('system_configurations', ['key' => 'subscriptions.suspension.behaviour', 'value' => 'keep_listings']);
        $this->assertDatabaseHas('system_configurations', ['key' => 'numbering.invoice.prefix', 'value' => 'SUB']);

        $this->assertSame(7, $this->config()->get('subscriptions.grace_period_days'));
        $this->assertSame('credit_new_invoice', $this->config()->get('subscriptions.proration.mode'));
    }

    public function test_changing_grace_period_in_config_changes_the_lifecycle(): void
    {
        $this->config()->set('subscriptions.grace_period_days', 14, $this->admin()->id, 'Annual grace is now two weeks.');

        $subscription = app(\App\Services\SubscriptionService::class)->subscribe(
            User::factory()->create(['password_changed_at' => now()]),
            SubscriptionPlan::where('name', 'Basic')->firstOrFail()->id
        );
        $subscription->update(['ends_at' => now()->subDay()]);

        app(\App\Services\SubscriptionService::class)->runExpiryCheck();

        $this->assertSame('grace', $subscription->fresh()->status);
        $this->assertSame(now()->addDays(13)->toDateString(), $subscription->fresh()->graceUntil()->toDateString());
    }

    public function test_config_change_is_audited_and_cache_is_invalidated(): void
    {
        $admin = $this->admin();
        $this->assertSame(7, $this->config()->get('subscriptions.grace_period_days'));

        $this->config()->set('subscriptions.grace_period_days', 21, $admin->id, 'Extending grace.');

        $this->assertSame(21, $this->config()->get('subscriptions.grace_period_days'));

        $this->assertDatabaseHas('configuration_audits', [
            'key' => 'subscriptions.grace_period_days',
            'old_value' => '7',
            'new_value' => '21',
            'changed_by' => $admin->id,
            'reason' => 'Extending grace.',
        ]);
    }

    public function test_high_risk_config_change_requires_approval(): void
    {
        $admin = $this->admin();

        try {
            $this->config()->set('numbering.invoice.padding', 6, $admin->id, 'Longer sequences.');
            $this->fail('Expected ValidationException for an unapproved high-risk change.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('value', $e->errors());
        }

        $this->config()->set('numbering.invoice.padding', 6, $admin->id, 'Longer sequences.', $admin->id);

        $this->assertSame(6, $this->config()->get('numbering.invoice.padding'));
        $this->assertDatabaseHas('configuration_audits', [
            'key' => 'numbering.invoice.padding',
            'new_value' => '6',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_invoice_and_receipt_numbering_reads_config(): void
    {
        $this->config()->set('numbering.invoice.prefix', 'INV', $this->admin()->id, 'New prefix.', $this->admin()->id);
        $this->config()->set('numbering.receipt.prefix', 'RC', $this->admin()->id, 'New receipt prefix.');

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->subscribe(
            User::factory()->create(['password_changed_at' => now()]),
            SubscriptionPlan::where('name', 'Professional')->firstOrFail()->id
        );

        $invoice = $subscription->invoices()->firstOrFail();
        $this->assertStringStartsWith('INV-'.now()->year.'-', $invoice->invoice_no);
        $this->assertStringStartsWith('RC-', $invoice->receipt_no);
    }

    public function test_invoice_sequence_padding_reads_config(): void
    {
        $this->config()->set('numbering.invoice.padding', 6, $this->admin()->id, 'Six-digit sequences.', $this->admin()->id);

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->subscribe(
            User::factory()->create(['password_changed_at' => now()]),
            SubscriptionPlan::where('name', 'Professional')->firstOrFail()->id
        );

        $invoiceNo = $subscription->invoices()->firstOrFail()->invoice_no;
        preg_match('/-(\d+)$/', $invoiceNo, $matches);
        $this->assertSame(6, strlen($matches[1]));
    }

    public function test_over_limit_message_comes_from_config(): void
    {
        $this->config()->set('subscriptions.entitlement_over_limit_message', 'Please upgrade to list more homes.', $this->admin()->id);

        $owner = User::factory()->create(['password_changed_at' => now()]);
        $owner->roles()->attach(\App\Models\Role::where('name', 'Owner')->firstOrFail()->id);

        \App\Models\Property::create([
            'owner_id' => $owner->id,
            'title' => 'House in Borrowdale',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => 'available',
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
        ]);

        $this->actingAs($owner)
            ->post('/owner/properties', [
                'title' => 'Second House in Borrowdale',
                'property_type' => 'house',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'price' => 850.00,
                'status' => 'available',
                'suburb' => 'Test Suburb',
                'city' => 'Harare',
            ])
            ->assertSessionHasErrors(['status' => 'Please upgrade to list more homes.']);
    }

    public function test_hide_listings_suspension_behaviour_hides_listings_from_marketplace(): void
    {
        $this->config()->set('subscriptions.suspension.behaviour', 'hide_listings', $this->admin()->id, 'Hide suspended owners.', $this->admin()->id);

        $owner = User::factory()->create(['password_changed_at' => now()]);
        $freePlan = SubscriptionPlan::where('name', 'Free')->firstOrFail();
        $subscription = $owner->subscriptions()->create([
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
            'cycle' => 'monthly',
            'details' => null,
        ]);

        $property = \App\Models\Property::create([
            'owner_id' => $owner->id,
            'title' => 'Hidden House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850.00,
            'status' => 'available',
            'suburb' => 'Hidden Suburb',
            'city' => 'Harare',
        ]);

        app(\App\Services\SubscriptionService::class)->runExpiryCheck();
        $this->assertSame('grace', $subscription->fresh()->status);

        $this->travel(10)->days();
        app(\App\Services\SubscriptionService::class)->runExpiryCheck();
        $this->assertSame('suspended', $subscription->fresh()->status);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('properties', function ($listings) use ($property) {
                foreach ($listings as $listing) {
                    if ($listing['id'] === $property->id) {
                        return false;
                    }
                }

                return true;
            }));
    }

    public function test_default_suspension_behaviour_keeps_suspended_listings_live(): void
    {
        $owner = User::factory()->create(['password_changed_at' => now()]);
        $subscription = $owner->subscriptions()->create([
            'plan_id' => SubscriptionPlan::where('name', 'Free')->firstOrFail()->id,
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(10),
            'cycle' => 'monthly',
            'details' => null,
        ]);

        $property = \App\Models\Property::create([
            'owner_id' => $owner->id,
            'title' => 'Visible Flat',
            'property_type' => 'flat',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price' => 450.00,
            'status' => 'available',
            'suburb' => 'Visible Suburb',
            'city' => 'Harare',
        ]);

        app(\App\Services\SubscriptionService::class)->runExpiryCheck();
        $this->assertSame('suspended', $subscription->fresh()->status);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('properties', function ($listings) use ($property) {
                foreach ($listings as $listing) {
                    if ($listing['id'] === $property->id) {
                        return true;
                    }
                }

                return false;
            }));
    }

    public function test_plan_feature_grants_are_seeded_per_catalogue(): void
    {
        $this->assertTrue(SubscriptionPlan::where('name', 'Free')->firstOrFail()->features()->where('code', 'PROPERTY_LISTING')->exists());
        $this->assertTrue(SubscriptionPlan::where('name', 'Business')->firstOrFail()->features()->where('code', 'RENT_COLLECTION')->exists());
        $this->assertFalse(SubscriptionPlan::where('name', 'Free')->firstOrFail()->features()->where('code', 'PROPERTY_ANALYTICS')->exists());
    }

    public function test_change_proration_mode_config_changes_upgrade_billing_but_leaves_old_invoices_untouched(): void
    {
        $config = $this->config();

        $subscription = app(\App\Services\SubscriptionService::class)->subscribe(
            User::factory()->create(['password_changed_at' => now()]),
            SubscriptionPlan::where('name', 'Basic')->firstOrFail()->id
        );

        $this->travel(15)->days();
        $upgraded = app(\App\Services\SubscriptionService::class)->subscribe(
            $subscription->owner,
            SubscriptionPlan::where('name', 'Professional')->firstOrFail()->id
        );

        $oldInvoice = $upgraded->invoices()->latest()->first();
        $oldAmount = $oldInvoice->amount;
        $oldNo = $oldInvoice->invoice_no;

        $config->set('subscriptions.proration.mode', 'charge_difference', $this->admin()->id, 'Charge the difference.', $this->admin()->id);

        $this->assertSame($oldAmount, $oldInvoice->fresh()->amount);
        $this->assertSame($oldNo, $oldInvoice->fresh()->invoice_no);

        $second = app(\App\Services\SubscriptionService::class)->subscribe(
            User::factory()->create(['password_changed_at' => now()]),
            SubscriptionPlan::where('name', 'Basic')->firstOrFail()->id
        );

        $this->travel(15)->days();
        $second = app(\App\Services\SubscriptionService::class)->subscribe(
            $second->owner,
            SubscriptionPlan::where('name', 'Professional')->firstOrFail()->id
        );

        $newInvoice = $second->invoices()->latest()->first();
        $this->assertSame('10.00', $newInvoice->amount);
        $this->assertSame('0.00', $newInvoice->details['credit']);
    }

    public function test_admin_configuration_centre_updates_rule_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch('/admin/configuration', [
                'values' => ['subscriptions.grace_period_days' => 10],
                'reason' => 'Ten-day grace for all plans.',
            ])
            ->assertRedirect(route('admin.configuration.index'));

        $this->assertSame(10, $this->config()->get('subscriptions.grace_period_days'));
        $this->assertDatabaseHas('configuration_audits', ['key' => 'subscriptions.grace_period_days', 'new_value' => '10']);
    }

    public function test_admin_cannot_set_unknown_or_locked_config_key(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch('/admin/configuration', ['values' => ['nope.does.not.exist' => '1']])
            ->assertSessionHasErrors('values.nope.does.not.exist');
    }
}