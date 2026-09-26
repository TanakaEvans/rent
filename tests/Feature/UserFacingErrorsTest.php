<?php

namespace Tests\Feature;

use App\Models\ExpressInterest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Broken business rules on in-app actions must read as a message on the page
 * the user came from (bootstrap/app.php), and pages that list a user's own
 * records must render once those records exist.
 */
class UserFacingErrorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tenant(): User
    {
        return User::where('email', 'tenant@dzimba.local')->firstOrFail();
    }

    public function test_a_rule_violation_on_a_browser_form_returns_to_the_page_with_a_message(): void
    {
        $property = Property::where('status', 'available')->firstOrFail();
        $property->update(['status' => 'reserved']);

        $this->actingAs($this->tenant())
            ->from(route('home'))
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('tenant.interests.store', $property->id))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This property is not accepting interest.');
    }

    public function test_a_missing_record_on_a_browser_form_gets_a_friendly_message(): void
    {
        $this->actingAs($this->tenant())
            ->from(route('tenant.interests.index'))
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('tenant.interests.withdraw', 999999))
            ->assertRedirect(route('tenant.interests.index'))
            ->assertSessionHas('error', 'That item is no longer available. Please refresh the page and try again.');
    }

    public function test_api_style_requests_and_page_loads_keep_their_status_codes(): void
    {
        $property = Property::where('status', 'available')->firstOrFail();
        $property->update(['status' => 'reserved']);

        $this->actingAs($this->tenant())
            ->post(route('tenant.interests.store', $property->id))
            ->assertNotFound();

        $this->get(route('property.show', 999999))->assertNotFound();
    }

    public function test_tenant_can_view_their_interests_page_with_existing_interests(): void
    {
        $tenant = $this->tenant();
        $this->assertTrue(ExpressInterest::where('tenant_id', $tenant->id)->exists(), 'Seeded tenant should have interests.');

        $this->actingAs($tenant)
            ->get(route('tenant.interests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Interests')
                ->has('interests.0.property.payment_terms'));
    }
}
