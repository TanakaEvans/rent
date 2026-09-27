<?php

namespace Tests\Feature\Fixes;

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\Role;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Services\LocationVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * inDrive-style location privacy: the public marketplace only ever exposes an
 * approximate area for a listing; the exact pin, street address and Google
 * Maps directions are revealed to entitled viewers only (owner, admin, or a
 * tenant with an accepted viewing / approved application / open lease). New
 * listings must carry a precise pin.
 */
class LocationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    // A real Harare point used as the "true" location in every fixture.
    private const LAT = -17.8250000;
    private const LNG = 31.0500000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function owner(): User
    {
        return $this->user('owner@dzimba.local');
    }

    private function newUser(string $role): User
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        return $user;
    }

    private function listing(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'owner_id' => $this->owner()->id,
            'title' => 'Pinned home',
            'description' => 'A tidy home.',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'deposit' => 500,
            'currency' => 'USD',
            'payment_terms' => 'monthly',
            'status' => 'available',
            'suburb' => 'Avondale',
            'zone' => 'Harare North',
            'city' => 'Harare',
            'address' => '12 Silver Street',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ], $overrides));
    }

    private function futureSlot(Property $property): ViewingSlot
    {
        return ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(11, 0),
            'status' => 'taken',
        ]);
    }

    /** The detail page shows the exact pin, address and directions flag. */
    private function assertExact($response, Property $property): void
    {
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('property.id', $property->id)
            ->where('property.locationExact', true)
            ->where('property.address', '12 Silver Street')
            ->where('property.latitude', fn ($lat) => round((float) $lat, 5) === round(self::LAT, 5))
            ->where('property.longitude', fn ($lng) => round((float) $lng, 5) === round(self::LNG, 5)));
    }

    /** The detail page shows only an approximate area, no street address. */
    private function assertApproximate($response, Property $property): void
    {
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('property.id', $property->id)
            ->where('property.locationExact', false)
            ->where('property.address', null)
            ->where('property.suburb', 'Avondale')
            ->where('property.city', 'Harare')
            ->where('property.latitude', fn ($lat) => round((float) $lat, 5) !== round(self::LAT, 5))
            ->where('property.longitude', fn ($lng) => round((float) $lng, 5) !== round(self::LNG, 5)));
    }

    public function test_guest_sees_only_the_approximate_area(): void
    {
        $property = $this->listing();

        $this->assertApproximate($this->get('/properties/'.$property->id), $property);
    }

    public function test_unrelated_tenant_sees_only_the_approximate_area(): void
    {
        $property = $this->listing();

        $this->assertApproximate(
            $this->actingAs($this->newUser('Tenant'))->get('/properties/'.$property->id),
            $property,
        );
    }

    public function test_owner_sees_the_exact_location(): void
    {
        $property = $this->listing();

        $this->assertExact($this->actingAs($this->owner())->get('/properties/'.$property->id), $property);
    }

    public function test_admin_and_staff_see_the_exact_location(): void
    {
        $property = $this->listing();

        foreach (['admin@system.local', 'staff@dzimba.local'] as $email) {
            $this->assertExact($this->actingAs($this->user($email))->get('/properties/'.$property->id), $property);
        }
    }

    public function test_tenant_with_an_accepted_viewing_sees_the_exact_location(): void
    {
        $property = $this->listing();
        $tenant = $this->newUser('Tenant');
        $slot = $this->futureSlot($property);

        ViewingRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'slot_id' => $slot->id,
            'status' => 'accepted',
        ]);

        $this->assertExact($this->actingAs($tenant)->get('/properties/'.$property->id), $property);
    }

    public function test_tenant_with_a_pending_viewing_still_sees_only_the_approximate_area(): void
    {
        $property = $this->listing();
        $tenant = $this->newUser('Tenant');
        $slot = $this->futureSlot($property);

        ViewingRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);

        $this->assertApproximate($this->actingAs($tenant)->get('/properties/'.$property->id), $property);
    }

    public function test_tenant_with_an_approved_application_sees_the_exact_location(): void
    {
        $property = $this->listing();
        $tenant = $this->newUser('Tenant');

        RentalApplication::create([
            'property_id' => $property->id,
            'applicant_id' => $tenant->id,
            'status' => 'approved',
        ]);

        $this->assertExact($this->actingAs($tenant)->get('/properties/'.$property->id), $property);
    }

    public function test_tenant_on_an_open_lease_sees_the_exact_location(): void
    {
        $property = $this->listing();
        $tenant = $this->newUser('Tenant');

        Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'lease_no' => 'LSE-LOC-1',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'rent_amount' => 500,
            'deposit_amount' => 500,
            'payment_terms' => 'monthly',
            'status' => 'active',
        ]);

        $this->assertExact($this->actingAs($tenant)->get('/properties/'.$property->id), $property);
    }

    public function test_marketplace_map_never_exposes_exact_coordinates(): void
    {
        $property = $this->listing(['title' => 'Zxqv Mapped Villa']);

        $this->actingAs($this->owner())->get('/?q=Zxqv')
            ->assertInertia(fn ($page) => $page
                ->where('properties.0.id', $property->id)
                ->where('properties.0.locationExact', false)
                ->where('properties.0.address', null)
                ->where('properties.0.latitude', fn ($lat) => round((float) $lat, 5) !== round(self::LAT, 5)));
    }

    public function test_creating_a_property_without_a_map_pin_is_rejected(): void
    {
        $payload = [
            'title' => 'Unpinned House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 850,
            'status' => 'available',
            'suburb' => 'Test Suburb',
            'city' => 'Harare',
            'currency' => 'USD',
            'payment_terms' => 'monthly',
            'security_type' => 'fenced',
            'minimum_stay' => 12,
            'preferred_tenant' => 'any',
            'landlord_type' => 'direct',
            'contact_preference' => 'platform',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ];

        $this->actingAs($this->owner())
            ->post('/owner/properties', $payload)
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_approximate_is_stable_per_property_but_differs_from_the_real_point(): void
    {
        $service = app(LocationVisibilityService::class);

        $first = $service->approximate(self::LAT, self::LNG, 500, 42);
        $second = $service->approximate(self::LAT, self::LNG, 500, 42);
        $other = $service->approximate(self::LAT, self::LNG, 500, 99);

        // Deterministic: the same seed always produces the same offset point.
        $this->assertSame($first, $second);

        // But it is never the exact point...
        $this->assertNotSame([self::LAT, self::LNG], [$first['lat'], $first['lng']]);
        $this->assertTrue(abs($first['lat'] - self::LAT) > 0 || abs($first['lng'] - self::LNG) > 0);

        // ...and different properties land on different circles.
        $this->assertNotSame($first, $other);
    }
}
