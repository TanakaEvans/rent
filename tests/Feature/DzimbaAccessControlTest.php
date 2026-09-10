<?php

namespace Tests\Feature;

use App\Models\AdPlacement;
use App\Models\User;
use App\Models\Role;
use App\Models\Property;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\RentInvoice;
use App\Services\PaymentService;
use App\Services\RentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DzimbaAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_owner_can_access_owner_dashboard(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner')->assertOk();
    }

    public function test_tenant_cannot_access_owner_dashboard(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_dashboard(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant')->assertForbidden();
    }

    public function test_tenant_can_access_tenant_dashboard(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/tenant')->assertOk();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_tenant_cannot_access_auth_management(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/auth/management')->assertForbidden();
    }

    public function test_admin_can_access_auth_management(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/auth/management')->assertOk();
    }

    public function test_owner_cannot_access_system_users(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/auth/users')->assertForbidden();
    }

    public function test_marketplace_home_renders(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Marketplace/Index'));
    }

    public function test_guest_can_view_public_property_detail(): void
    {
        $property = \App\Models\Property::where('status', 'available')->first();
        $this->get('/properties/'.$property->id)->assertOk()->assertInertia(fn ($page) => $page->component('Marketplace/Show'));
    }

    public function test_owner_can_access_my_properties(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties')->assertOk();
    }

    public function test_owner_can_access_create_property(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/create')->assertOk();
    }

    public function test_owner_can_access_enquiry_inbox(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/enquiries')->assertOk();
    }

    public function test_tenant_cannot_access_owner_enquiry_inbox(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/enquiries')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_enquiries(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/enquiries')->assertForbidden();
    }

    public function test_tenant_can_access_own_enquiries(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/tenant/enquiries')->assertOk();
    }

    public function test_guest_cannot_access_enquiry_routes(): void
    {
        $this->get('/owner/enquiries')->assertRedirect(route('login'));
        $this->get('/tenant/enquiries')->assertRedirect(route('login'));
    }

    public function test_owner_can_access_viewing_slots_for_their_property(): void
    {
        $property = \App\Models\Property::first();
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/'.$property->id.'/slots')->assertOk();
    }

    public function test_tenant_cannot_access_viewing_slot_routes(): void
    {
        $property = \App\Models\Property::first();
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/properties/'.$property->id.'/slots')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/properties/'.$property->id.'/slots', [])->assertForbidden();
    }

    public function test_guest_cannot_access_viewing_slot_routes(): void
    {
        $property = \App\Models\Property::first();
        $this->get('/owner/properties/'.$property->id.'/slots')->assertRedirect(route('login'));
    }

    public function test_owner_cannot_access_viewing_slots_of_another_owners_property(): void
    {
        $otherOwner = User::factory()->create(['name' => 'Other Owner']);
        $otherOwner->roles()->attach(Role::where('name', 'Owner')->first()->id);
        $other = \App\Models\Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Someone Else',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 700,
            'status' => 'available',
            'city' => 'Bulawayo',
        ]);
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/properties/'.$other->id.'/slots')->assertNotFound();
    }

    public function test_owner_can_access_viewing_requests(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/viewings')->assertOk();
    }

    public function test_tenant_cannot_access_owner_viewing_routes(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/viewings')->assertForbidden();
    }

    public function test_owner_cannot_access_tenant_viewings(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/viewings')->assertForbidden();
    }

    public function test_tenant_can_access_own_viewings(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/viewings')->assertOk();
    }

    public function test_guest_cannot_access_viewing_request_routes(): void
    {
        $this->get('/owner/viewings')->assertRedirect(route('login'));
        $this->get('/tenant/viewings')->assertRedirect(route('login'));
        $this->post('/tenant/viewings', [])->assertRedirect(route('login'));
    }

    public function test_owner_can_access_applications_review(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/applications')->assertOk();
    }

    public function test_tenant_cannot_access_owner_applications_review(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/applications')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/applications/1/approve')->assertForbidden();
    }

    public function test_tenant_can_access_own_applications(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/applications')->assertOk();
    }

    public function test_owner_cannot_access_tenant_applications(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/applications')->assertForbidden();
        $this->actingAs($owner)->post('/tenant/applications/1', [])->assertForbidden();
    }

    public function test_guest_cannot_access_application_routes(): void
    {
        $this->get('/owner/applications')->assertRedirect(route('login'));
        $this->get('/tenant/applications')->assertRedirect(route('login'));
        $this->post('/tenant/applications/1', [])->assertRedirect(route('login'));
    }

    public function test_owner_can_access_own_leases(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/leases')->assertOk();
    }

    public function test_tenant_cannot_access_owner_lease_routes(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/leases')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/applications/1/lease', [])->assertForbidden();
        $this->actingAs($tenant)->post('/owner/leases/1/send', [])->assertForbidden();
        $this->actingAs($tenant)->post('/owner/leases/1/sign', [])->assertForbidden();
        $this->actingAs($tenant)->post('/owner/leases/1/renew', [])->assertForbidden();
    }

    public function test_tenant_can_access_own_leases(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/leases')->assertOk();
    }

    public function test_owner_cannot_access_tenant_leases(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/leases')->assertForbidden();
        $this->actingAs($owner)->post('/tenant/leases/1/sign', [])->assertForbidden();
    }

    public function test_guest_cannot_access_lease_routes(): void
    {
        $this->get('/owner/leases')->assertRedirect(route('login'));
        $this->get('/tenant/leases')->assertRedirect(route('login'));
        $this->post('/owner/applications/1/lease', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/send', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/sign', [])->assertRedirect(route('login'));
        $this->post('/owner/leases/1/renew', [])->assertRedirect(route('login'));
        $this->post('/tenant/leases/1/sign', [])->assertRedirect(route('login'));
    }

    public function test_any_authenticated_role_can_access_own_notifications(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/notifications')->assertOk();
        $this->actingAs(User::where('username', 'tenant')->first())->get('/notifications')->assertOk();
        $this->actingAs(User::where('username', 'admin')->first())->get('/notifications')->assertOk();
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
        $this->post('/notifications/read-all')->assertRedirect(route('login'));
    }

    public function test_tenant_cannot_access_my_properties(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/properties')->assertForbidden();
    }

    public function test_tenant_cannot_store_property(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->post('/owner/properties', [])->assertForbidden();
    }

    public function test_admin_cannot_access_owner_properties(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/owner/properties')->assertForbidden();
    }

    public function test_owner_can_change_own_property_status(): void
    {
        $owner = User::where('username', 'owner')->first();
        $property = \App\Models\Property::where('owner_id', $owner->id)->first();
        $this->actingAs($owner)->put('/owner/properties/'.$property->id.'/status', ['status' => 'unavailable'])->assertRedirect();
    }

    public function test_tenant_cannot_change_property_status(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $property = \App\Models\Property::where('owner_id', User::where('username', 'owner')->first()->id)->first();
        $this->actingAs($tenant)->put('/owner/properties/'.$property->id.'/status', ['status' => 'unavailable'])->assertForbidden();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    private function seededDocument(): Document
    {
        $document = Document::where('type', 'lease_agreement')->first();
        $this->assertNotNull($document, 'Expected a seeded agreement document for the demo lease.');

        return $document;
    }

    public function test_owner_can_access_own_documents(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/documents')->assertOk();
    }

    public function test_tenant_cannot_access_owner_documents(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/documents')->assertForbidden();
    }

    public function test_tenant_can_access_own_documents(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/tenant/documents')->assertOk();
    }

    public function test_owner_cannot_access_tenant_documents(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/tenant/documents')->assertForbidden();
    }

    public function test_parties_can_view_a_seeded_agreement(): void
    {
        $document = $this->seededDocument();

        $this->actingAs(User::where('username', 'owner')->first())->get('/documents/'.$document->id)->assertOk();
        $this->actingAs(User::where('username', 'tenant')->first())->get('/documents/'.$document->id)->assertOk();
    }

    public function test_admin_can_view_any_document(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/documents/'.$this->seededDocument()->id)->assertOk();
    }

    public function test_stranger_cannot_view_others_agreement(): void
    {
        $stranger = User::factory()->create(['name' => 'Stranger Tenant', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $this->actingAs($stranger)->get('/documents/'.$this->seededDocument()->id)->assertNotFound();
    }

    public function test_guest_cannot_access_document_routes(): void
    {
        $this->get('/owner/documents')->assertRedirect(route('login'));
        $this->get('/tenant/documents')->assertRedirect(route('login'));
        $this->get('/documents/1')->assertRedirect(route('login'));
        $this->get('/documents/1/download')->assertRedirect(route('login'));
    }

    public function test_owner_can_access_own_subscription(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/subscriptions')->assertOk();
    }

    public function test_tenant_cannot_access_owner_subscription_page(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/subscriptions')->assertForbidden();
    }

    public function test_admin_can_access_subscription_plans(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/admin/subscriptions/plans')->assertOk();
    }

    public function test_tenant_cannot_access_subscription_plans(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/admin/subscriptions/plans')->assertForbidden();
    }

    public function test_owner_cannot_access_subscription_plans(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/admin/subscriptions/plans')->assertForbidden();
    }

    public function test_guest_redirected_from_subscription_routes(): void
    {
        $this->get('/owner/subscriptions')->assertRedirect(route('login'));
        $this->get('/admin/subscriptions/plans')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_configuration_centre(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/admin/configuration')->assertOk();
    }

    public function test_non_admins_cannot_access_configuration_centre(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $owner = User::where('username', 'owner')->first();

        $this->actingAs($tenant)->get('/admin/configuration')->assertForbidden();
        $this->actingAs($owner)->get('/admin/configuration')->assertForbidden();
    }

    public function test_guest_redirected_from_configuration_centre(): void
    {
        $this->get('/admin/configuration')->assertRedirect(route('login'));
        $this->patch('/admin/configuration')->assertRedirect(route('login'));
    }

    public function test_admin_can_toggle_plan_features(): void
    {
        $admin = User::where('username', 'admin')->first();
        $plan = \App\Models\SubscriptionPlan::where('name', 'Free')->first();

        $this->actingAs($admin)
            ->put('/admin/subscriptions/plans/'.$plan->id.'/features', ['feature_ids' => []])
            ->assertRedirect(route('admin.subscriptions.plans.index'));
    }

    public function test_owner_cannot_toggle_plan_features(): void
    {
        $owner = User::where('username', 'owner')->first();
        $plan = \App\Models\SubscriptionPlan::where('name', 'Free')->first();

        $this->actingAs($owner)
            ->put('/admin/subscriptions/plans/'.$plan->id.'/features', ['feature_ids' => []])
            ->assertForbidden();
    }

    public function test_owner_can_access_own_rent_page(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/rent')->assertOk();
    }

    public function test_tenant_cannot_access_owner_rent_page(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/owner/rent')->assertForbidden();
    }

    public function test_tenant_can_access_own_rent_page(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/rent')->assertOk();
    }

    public function test_owner_cannot_access_tenant_rent_page(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/tenant/rent')->assertForbidden();
    }

    public function test_guest_redirected_from_rent_routes(): void
    {
        $this->get('/owner/rent')->assertRedirect(route('login'));
        $this->get('/tenant/rent')->assertRedirect(route('login'));
        $this->post('/tenant/rent/1/pay', [])->assertRedirect(route('login'));
        $this->get('/tenant/rent/payments/1/receipt')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_payment_approvals(): void
    {
        $this->actingAs(User::where('username', 'admin')->first())->get('/admin/rent/payments')->assertOk();
    }

    public function test_tenant_cannot_access_payment_approvals(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/admin/rent/payments')->assertForbidden();
    }

    public function test_owner_cannot_access_payment_approvals(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/admin/rent/payments')->assertForbidden();
    }

    public function test_guest_redirected_from_payment_approvals(): void
    {
        $this->get('/admin/rent/payments')->assertRedirect(route('login'));
    }

    public function test_tenant_can_access_saved_searches(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/saved-searches')->assertOk();
    }

    public function test_owner_cannot_access_tenant_saved_searches(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/tenant/saved-searches')->assertForbidden();
    }

    public function test_guest_redirected_from_saved_search_routes(): void
    {
        $this->get('/tenant/saved-searches')->assertRedirect(route('login'));
        $this->post('/tenant/saved-searches', ['name' => 'X', 'criteria' => ['city' => 'Harare']])->assertRedirect(route('login'));
    }

    public function test_tenant_can_access_own_reports_page(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/tenant/reports')->assertOk();
    }

    public function test_owner_cannot_access_tenant_reports_page(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/tenant/reports')->assertForbidden();
    }

    public function test_guest_cannot_access_tenant_reports_page(): void
    {
        $this->get('/tenant/reports')->assertRedirect(route('login'));
    }

    public function test_owner_can_access_owner_analytics(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/owner/analytics')->assertOk();
    }

    public function test_tenant_cannot_access_owner_analytics(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/owner/analytics')->assertForbidden();
    }

    public function test_guest_cannot_access_owner_analytics(): void
    {
        $this->get('/owner/analytics')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_marketplace_analytics(): void
    {
        $this->actingAs(User::where('username', 'admin')->first())->get('/admin/marketplace/analytics')->assertOk();
    }

    public function test_tenant_cannot_access_marketplace_analytics(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/admin/marketplace/analytics')->assertForbidden();
    }

    public function test_owner_cannot_access_marketplace_analytics(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/admin/marketplace/analytics')->assertForbidden();
    }

    public function test_admin_can_access_marketplace_reports_queue(): void
    {
        $this->actingAs(User::where('username', 'admin')->first())->get('/admin/marketplace/reports')->assertOk();
    }

    public function test_tenant_cannot_access_marketplace_reports_queue(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())->get('/admin/marketplace/reports')->assertForbidden();
    }

    public function test_owner_cannot_access_marketplace_reports_queue(): void
    {
        $this->actingAs(User::where('username', 'owner')->first())->get('/admin/marketplace/reports')->assertForbidden();
    }

    public function test_guest_cannot_access_marketplace_reports_queue(): void
    {
        $this->get('/admin/marketplace/reports')->assertRedirect(route('login'));
    }

    public function test_admin_can_transition_a_marketplace_report(): void
    {
        $property = \App\Models\Property::where('status', 'available')->first();
        $report = \App\Models\Report::create([
            'reporter_id' => null,
            'subject_type' => 'property',
            'subject_id' => $property->id,
            'category' => 'wrong_price',
            'description' => 'Listed price differs from the advert.',
            'status' => 'open',
        ]);

        $this->actingAs(User::where('username', 'admin')->first())
            ->post('/admin/marketplace/reports/'.$report->id.'/under_review')
            ->assertRedirect();
    }

    public function test_tenant_cannot_transition_a_marketplace_report(): void
    {
        $this->actingAs(User::where('username', 'tenant')->first())
            ->post('/admin/marketplace/reports/1/under_review')
            ->assertForbidden();
    }

    public function test_guest_cannot_transition_a_marketplace_report(): void
    {
        $this->post('/admin/marketplace/reports/1/under_review')->assertRedirect(route('login'));
    }

    public function test_guests_can_report_a_listing_without_an_account(): void
    {
        $property = \App\Models\Property::where('status', 'available')->first();

        $this->post('/properties/'.$property->id.'/report', [
            'subject_type' => 'property',
            'subject_id' => $property->id,
            'category' => 'fraud_concern',
            'description' => 'Suspect listing, please check.',
        ])->assertRedirect();
    }

    private function duePosition(): array
    {
        $owner = User::where('username', 'owner')->first();
        $tenant = User::where('username', 'tenant')->first();

        $property = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Rent Access Test House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500.00,
            'status' => 'available',
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
        ]);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-ACCESS-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);

        app(RentService::class)->generateFor($lease);
        app(RentService::class)->runInvoiceLifecycle();

        return [$tenant, RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail()];
    }

    public function test_tenant_can_pay_own_due_invoice_and_download_issued_receipt(): void
    {
        [$tenant, $invoice] = $this->duePosition();

        $this->actingAs($tenant)
            ->post('/tenant/rent/'.$invoice->id.'/pay', [
                'amount' => '500.00',
                'method' => 'cash',
                'reference' => 'Branch payment',
            ])
            ->assertRedirect(route('tenant.rent.index'));

        $payment = Payment::where('invoice_id', $invoice->id)->where('status', 'pending')->firstOrFail();
        app(PaymentService::class)->approve(User::where('username', 'admin')->first(), $payment);

        $this->actingAs($tenant)
            ->get('/tenant/rent/payments/'.$payment->id.'/receipt')
            ->assertOk();
    }

    public function test_tenant_cannot_download_another_tenants_receipt(): void
    {
        $stranger = User::factory()->create(['name' => 'Receipt Stranger', 'password_changed_at' => now()]);
        $stranger->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $payment = Payment::where('status', 'pending')->first();
        $this->assertNotNull($payment, 'Expected a seeded pending payment for the demo lease.');

        $this->actingAs($stranger)
            ->get('/tenant/rent/payments/'.$payment->id.'/receipt')
            ->assertNotFound();
    }

    public function test_tenant_cannot_pay_another_tenants_invoice(): void
    {
        $otherTenant = User::factory()->create(['name' => 'Other Tenant', 'email' => 'access-other@example.test', 'password_changed_at' => now()]);
        $otherTenant->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        $owner = User::where('username', 'owner')->first();
        $property = Property::create([
            'owner_id' => $owner->id,
            'title' => 'Rent Scoping House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500.00,
            'status' => 'available',
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
        ]);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $otherTenant->id,
            'application_id' => null,
            'lease_no' => 'LSE-TEST-SCOPE-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'start_date' => now(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'rent_amount' => 500.00,
            'deposit_amount' => 0,
            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
            'status' => 'active',
            'clause_version' => 1,
        ]);

        app(RentService::class)->generateFor($lease);
        app(RentService::class)->runInvoiceLifecycle();
        $invoice = RentInvoice::where('lease_id', $lease->id)->where('status', 'due')->firstOrFail();

        $this->actingAs(User::where('username', 'tenant')->first())
            ->post('/tenant/rent/'.$invoice->id.'/pay', [
                'amount' => '500.00',
                'method' => 'cash',
            ])
            ->assertNotFound();
    }

    // ---------- Wave 4 slice 6: featured & advertising ----------

    public function test_owner_can_access_owner_advertising(): void
    {
        $owner = User::where('username', 'owner')->first();
        $this->actingAs($owner)->get('/owner/advertising')->assertOk();
    }

    public function test_tenant_cannot_access_owner_advertising(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($tenant)->get('/owner/advertising')->assertForbidden();
        $this->actingAs($tenant)->post('/owner/advertising', [])->assertForbidden();
    }

    public function test_guest_cannot_access_owner_advertising(): void
    {
        $this->get('/owner/advertising')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_admin_advertising(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/admin/advertising')->assertOk();
    }

    public function test_owner_tenant_cannot_access_admin_advertising(): void
    {
        $owner = User::where('username', 'owner')->first();
        $tenant = User::where('username', 'tenant')->first();
        $this->actingAs($owner)->get('/admin/advertising')->assertForbidden();
        $this->actingAs($tenant)->get('/admin/advertising')->assertForbidden();
    }

    public function test_tenant_cannot_moderate_advertising_approvals(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $reserved = AdPlacement::where('status', 'reserved')->first();
        $this->assertNotNull($reserved, 'Expected a seeded reserved ad placement.');

        $this->actingAs($tenant)
            ->post(route('admin.advertising.approve', ['placement' => $reserved->id]))
            ->assertForbidden();
        $this->actingAs($tenant)
            ->post(route('admin.advertising.cancel', ['placement' => $reserved->id]))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_advertising(): void
    {
        $this->get('/admin/advertising')->assertRedirect(route('login'));
    }
}