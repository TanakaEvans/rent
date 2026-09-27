<?php

namespace Tests\Feature\Fixes;

use App\Http\Controllers\AccessController;
use App\Http\Middleware\EnsureHasAccess;
use App\Models\AccessPass;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The configurable free-then-paid access model (Module 24 — `access.*`).
 *
 * The gate middleware is attached to action routes in routes/web.php (owned by
 * another engineer). To exercise it without touching that shared file, this
 * suite registers synthetic routes bound to the same controller and middleware,
 * which behave exactly as the real wiring will.
 */
class AccessPassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/access', [AccessController::class, 'index'])->name('access.index');
            Route::post('/access/purchase', [AccessController::class, 'purchase'])->name('access.purchase');

            // Stand-ins for the real gated action routes (owner listing-create;
            // tenant enquire/apply/express-interest).
            Route::post('/__gated/listing', fn () => response('listing-created'))
                ->middleware(EnsureHasAccess::class)->name('test.listing.create');
            Route::post('/__gated/enquiry', fn () => response('enquiry-sent'))
                ->middleware(EnsureHasAccess::class)->name('test.enquiry');
        });

        // Runtime-registered routes must have their name lookups rebuilt so the
        // route() helper (used by the controller redirects and assertions) resolves.
        Route::getRoutes()->refreshNameLookups();
    }

    private function owner(): User
    {
        return User::where('username', 'owner')->first();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->first();
    }

    private function access(): AccessService
    {
        return app(AccessService::class);
    }

    private function setConfig(string $key, string $encoded): void
    {
        \App\Models\SystemConfiguration::where('key', $key)->update(['value' => $encoded]);
        Cache::forget('dzimba.config.'.$key);
        app(ConfigurationService::class)->forgetMemo('dzimba.config.'.$key);
    }

    /** Enable charging and close the free window, with the given payer. */
    private function chargeNow(string $payer = 'owner'): void
    {
        $this->setConfig('access.charge_enabled', '1');
        $this->setConfig('access.free_until', '2020-01-01');
        $this->setConfig('access.payer', $payer);
    }

    // ---------------------------------------------------------------------
    // Charging OFF (shipped default) — a complete no-op.
    // ---------------------------------------------------------------------

    public function test_with_charging_off_nobody_requires_a_pass_and_all_actions_work(): void
    {
        $this->assertFalse($this->access()->requiresPass($this->owner()));
        $this->assertFalse($this->access()->requiresPass($this->tenant()));
        $this->assertTrue($this->access()->hasActiveAccess($this->owner()));
        $this->assertTrue($this->access()->hasActiveAccess($this->tenant()));

        $this->actingAs($this->owner())->post('/__gated/listing')->assertOk()->assertSee('listing-created');
        $this->actingAs($this->tenant())->post('/__gated/enquiry')->assertOk()->assertSee('enquiry-sent');
    }

    public function test_status_reports_free_while_charging_off(): void
    {
        $status = $this->access()->status($this->owner());

        $this->assertFalse($status['required']);
        $this->assertTrue($status['active']);
        $this->assertSame('2027-03-27', $status['free_until']);
        $this->assertSame('1.00', $status['price']);
        $this->assertSame('USD', $status['currency']);
        $this->assertNull($status['ends_at']);
    }

    // ---------------------------------------------------------------------
    // Charging ON but still inside the free window.
    // ---------------------------------------------------------------------

    public function test_charging_on_before_free_until_is_still_free(): void
    {
        $this->setConfig('access.charge_enabled', '1');
        // free_until stays at its default (2027-03-27, in the future).

        $this->assertFalse($this->access()->requiresPass($this->owner()));
        $this->actingAs($this->owner())->post('/__gated/listing')->assertOk();
    }

    // ---------------------------------------------------------------------
    // Free window closed — the gate bites, scoped to the payer role.
    // ---------------------------------------------------------------------

    public function test_after_free_until_owner_without_pass_is_redirected_from_listing_create(): void
    {
        $this->chargeNow('owner');

        $this->assertTrue($this->access()->requiresPass($this->owner()));

        $this->actingAs($this->owner())
            ->post('/__gated/listing')
            ->assertRedirect(route('access.index'));
    }

    public function test_payer_owner_does_not_gate_tenants(): void
    {
        $this->chargeNow('owner');

        $this->assertFalse($this->access()->requiresPass($this->tenant()));
        $this->actingAs($this->tenant())->post('/__gated/enquiry')->assertOk();
    }

    public function test_payer_tenant_gates_tenants_but_not_owners(): void
    {
        $this->chargeNow('tenant');

        $this->assertTrue($this->access()->requiresPass($this->tenant()));
        $this->actingAs($this->tenant())
            ->post('/__gated/enquiry')
            ->assertRedirect(route('access.index'));

        $this->assertFalse($this->access()->requiresPass($this->owner()));
        $this->actingAs($this->owner())->post('/__gated/listing')->assertOk();
    }

    public function test_payer_both_gates_owners_and_tenants(): void
    {
        $this->chargeNow('both');

        $this->assertTrue($this->access()->requiresPass($this->owner()));
        $this->assertTrue($this->access()->requiresPass($this->tenant()));
    }

    // ---------------------------------------------------------------------
    // Buying a pass grants access for the configured period.
    // ---------------------------------------------------------------------

    public function test_buying_a_pass_grants_access_for_the_period(): void
    {
        $this->chargeNow('owner');
        $owner = $this->owner();

        $this->assertFalse($this->access()->hasActiveAccess($owner));

        $this->actingAs($owner)
            ->post(route('access.purchase'))
            ->assertRedirect(route('access.index'))
            ->assertSessionHas('success');

        $this->assertTrue($this->access()->hasActiveAccess($owner->fresh()));

        $pass = AccessPass::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('1.00', $pass->amount);
        $this->assertSame('USD', $pass->currency);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $pass->ends_at->timestamp,
            60,
            'A pass lasts the configured period_days.'
        );

        // The action now goes through.
        $this->actingAs($owner->fresh())->post('/__gated/listing')->assertOk()->assertSee('listing-created');
    }

    public function test_purchase_is_a_no_op_while_access_is_free(): void
    {
        // Charging off — buying should record nothing and warn instead.
        $this->actingAs($this->owner())
            ->post(route('access.purchase'))
            ->assertRedirect(route('access.index'))
            ->assertSessionHas('warning');

        $this->assertSame(0, AccessPass::count());
    }

    // ---------------------------------------------------------------------
    // Expired passes block again.
    // ---------------------------------------------------------------------

    public function test_expired_pass_blocks_again(): void
    {
        $this->chargeNow('owner');
        $owner = $this->owner();

        AccessPass::create([
            'user_id' => $owner->id,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDay(),
            'amount' => '1.00',
            'currency' => 'USD',
            'reference' => 'ACCESS-EXPIRED',
        ]);

        $this->assertFalse($this->access()->hasActiveAccess($owner));
        $this->actingAs($owner)
            ->post('/__gated/listing')
            ->assertRedirect(route('access.index'));
    }

    public function test_access_page_renders_with_status(): void
    {
        $this->chargeNow('owner');

        $this->actingAs($this->owner())
            ->get(route('access.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Access/Index')
                ->where('status.required', true)
                ->where('status.active', false));
    }
}
