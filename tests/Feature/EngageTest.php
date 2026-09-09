<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngageTest extends TestCase
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

    private function availableProperty(): Property
    {
        return Property::listed()->first();
    }

    public function test_tenant_can_send_an_enquiry(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();

        $this->actingAs($tenant)->post('/tenant/enquiries/'.$property->id, [
            'message' => 'Is this still available?',
            'phone' => '+263 77 000 1111',
        ])->assertRedirect();

        $this->assertDatabaseHas('enquiries', [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'message' => 'Is this still available?',
            'phone' => '+263 77 000 1111',
            'status' => 'new',
        ]);
    }

    public function test_enquiry_requires_a_message(): void
    {
        $property = $this->availableProperty();

        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => ''])
            ->assertSessionHasErrors('message');
    }

    public function test_enquiry_message_is_limited_to_1000_chars(): void
    {
        $property = $this->availableProperty();

        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('message');
    }

    public function test_guest_cannot_send_an_enquiry(): void
    {
        $property = $this->availableProperty();

        $this->post('/tenant/enquiries/'.$property->id, ['message' => 'Hello'])
            ->assertRedirect(route('login'));
    }

    public function test_cannot_enquire_about_unavailable_property(): void
    {
        $property = $this->availableProperty();
        $property->update(['status' => 'reserved']);

        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => 'Hello'])
            ->assertNotFound();
    }

    public function test_duplicate_open_enquiry_is_blocked(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();

        $this->actingAs($tenant)->post('/tenant/enquiries/'.$property->id, ['message' => 'First enquiry'])
            ->assertRedirect();

        $this->actingAs($tenant)->post('/tenant/enquiries/'.$property->id, ['message' => 'Second enquiry'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1, Enquiry::where('property_id', $property->id)->where('tenant_id', $tenant->id)->count());
    }

    public function test_owner_can_reply_and_thread_moves_to_replied(): void
    {
        $tenant = $this->tenant();
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'message' => 'Room for a double bed?',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', [
            'reply' => 'Yes, fits a king bed comfortably.',
        ])->assertRedirect();

        $fresh = $enquiry->fresh();
        $this->assertSame('replied', $fresh->status);
        $this->assertSame('Yes, fits a king bed comfortably.', $fresh->reply);
        $this->assertNotNull($fresh->replied_at);
    }

    public function test_owner_cannot_reply_to_another_owners_enquiry(): void
    {
        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign House',
            'property_type' => 'house',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 900,
            'status' => 'available',
            'city' => 'Harare',
            'suburb' => 'Borrowdale',
        ]);
        $enquiry = Enquiry::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi there',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Not yours'])
            ->assertNotFound();
    }

    public function test_reply_requires_a_body(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => ''])
            ->assertSessionHasErrors('reply');
    }

    public function test_cannot_reply_to_a_closed_thread(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi',
            'status' => 'closed',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Too late'])
            ->assertSessionHasErrors('reply');

        $this->assertSame('closed', $enquiry->fresh()->status);
    }

    public function test_owner_can_close_an_enquiry(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi',
            'status' => 'replied',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/close')
            ->assertRedirect();

        $this->assertSame('closed', $enquiry->fresh()->status);
    }

    public function test_owner_inbox_only_contains_own_properties_enquiries(): void
    {
        Enquiry::query()->delete();
        $property = $this->availableProperty();
        Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'My enquiry',
            'status' => 'new',
        ]);

        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Someone Elses House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'status' => 'available',
            'city' => 'Harare',
        ]);
        Enquiry::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Not for them',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->get('/owner/enquiries')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Enquiries/Index')
                ->has('properties', 1)
                ->where('properties.0.id', $property->id)
                ->where('properties.0.enquiries.0.message', 'My enquiry'));
    }

    public function test_owner_show_marks_enquiry_as_read(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->get('/owner/enquiries/'.$enquiry->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Enquiries/Show')
                ->where('enquiry.id', $enquiry->id)
                ->where('enquiry.status', 'read'));

        $fresh = $enquiry->fresh();
        $this->assertSame('read', $fresh->status);
        $this->assertNotNull($fresh->read_at);
    }

    public function test_owner_cannot_open_another_owners_thread(): void
    {
        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign Thread',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 400,
            'status' => 'available',
            'city' => 'Harare',
        ]);
        $enquiry = Enquiry::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hi',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->get('/owner/enquiries/'.$enquiry->id)
            ->assertNotFound();
    }

    public function test_tenant_only_sees_their_own_enquiries(): void
    {
        Enquiry::query()->delete();
        $property = $this->availableProperty();
        Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Mine',
            'status' => 'new',
        ]);

        $otherTenant = $this->newTenant();
        Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $otherTenant->id,
            'message' => 'Someone else',
            'status' => 'new',
        ]);

        $this->actingAs($this->tenant())->get('/tenant/enquiries')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Enquiries')
                ->has('enquiries', 1)
                ->where('enquiries.0.tenant_id', $this->tenant()->id)
                ->where('enquiries.0.message', 'Mine'));
    }

    public function test_owner_can_create_a_viewing_slot(): void
    {
        $property = $this->availableProperty();

        $this->actingAs($this->owner())->post('/owner/properties/'.$property->id.'/slots', [
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->addHour()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertDatabaseHas('viewing_slots', [
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'status' => 'available',
        ]);
    }

    public function test_slot_start_must_be_in_the_future(): void
    {
        $property = $this->availableProperty();

        $this->actingAs($this->owner())->post('/owner/properties/'.$property->id.'/slots', [
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_slot_must_end_after_it_starts(): void
    {
        $property = $this->availableProperty();

        $this->actingAs($this->owner())->post('/owner/properties/'.$property->id.'/slots', [
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_owner_can_update_an_available_slot(): void
    {
        $property = $this->availableProperty();
        $slot = \App\Models\ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'status' => 'available',
        ]);

        $this->actingAs($this->owner())->put('/owner/properties/'.$property->id.'/slots/'.$slot->id, [
            'starts_at' => now()->addDays(3)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->setTime(15, 0)->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $fresh = $slot->fresh();
        $this->assertSame('14:00', $fresh->starts_at->format('H:i'));
        $this->assertSame('15:00', $fresh->ends_at->format('H:i'));
    }

    public function test_owner_can_delete_an_available_slot(): void
    {
        $property = $this->availableProperty();
        $slot = \App\Models\ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'status' => 'available',
        ]);

        $this->actingAs($this->owner())->delete('/owner/properties/'.$property->id.'/slots/'.$slot->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('viewing_slots', ['id' => $slot->id]);
    }

    public function test_slot_management_is_isolated_per_owner(): void
    {
        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Not Mine',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 500,
            'status' => 'available',
            'city' => 'Harare',
        ]);

        $this->actingAs($this->owner())
            ->post('/owner/properties/'.$otherProperty->id.'/slots', [
                'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(2)->addHour()->format('Y-m-d\TH:i'),
            ])->assertNotFound();
    }

    public function test_slots_page_lists_only_this_propertys_slots(): void
    {
        $property = $this->availableProperty();
        $other = Property::create([
            'owner_id' => $this->owner()->id,
            'title' => 'Other House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 400,
            'status' => 'available',
            'city' => 'Harare',
        ]);
        \App\Models\ViewingSlot::create([
            'property_id' => $other->id,
            'owner_id' => $this->owner()->id,
            'starts_at' => now()->addDays(4)->setTime(9, 0),
            'ends_at' => now()->addDays(4)->setTime(10, 0),
            'status' => 'available',
        ]);

        $this->actingAs($this->owner())->get('/owner/properties/'.$property->id.'/slots')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/ViewingSlots/Index')
                ->where('property.id', $property->id)
                ->has('slots', 3)
            );
    }

    private function futureSlot(Property $property, int $dayOffset = 5): \App\Models\ViewingSlot
    {
        return \App\Models\ViewingSlot::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'starts_at' => now()->addDays($dayOffset)->setTime(10, 0),
            'ends_at' => now()->addDays($dayOffset)->setTime(11, 0),
            'status' => 'available',
        ]);
    }

    private function requestedBooking(): \App\Models\ViewingRequest
    {
        $slot = $this->futureSlot($this->availableProperty());

        return \App\Models\ViewingRequest::create([
            'property_id' => $slot->property_id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'request_message' => 'Can I come with a friend?',
            'status' => 'requested',
        ]);
    }

    public function test_tenant_can_request_a_viewing(): void
    {
        $property = $this->availableProperty();
        $slot = $this->futureSlot($property);

        $this->actingAs($this->tenant())->post('/tenant/viewings', [
            'property_id' => $property->id,
            'slot_id' => $slot->id,
            'request_message' => 'Morning works best for me.',
        ])->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', [
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);
    }

    public function test_request_message_is_limited(): void
    {
        $property = $this->availableProperty();
        $slot = $this->futureSlot($property);

        $this->actingAs($this->tenant())->post('/tenant/viewings', [
            'property_id' => $property->id,
            'slot_id' => $slot->id,
            'request_message' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('request_message');
    }

    public function test_cannot_request_on_a_taken_slot(): void
    {
        $property = $this->availableProperty();
        $slot = $this->futureSlot($property);
        $slot->update(['status' => 'taken']);

        $this->actingAs($this->tenant())->post('/tenant/viewings', [
            'property_id' => $property->id,
            'slot_id' => $slot->id,
        ])->assertStatus(409);
    }

    public function test_accept_locks_the_slot(): void
    {
        $booking = $this->requestedBooking();

        $this->actingAs($this->owner())
            ->post('/owner/viewings/'.$booking->id.'/accept')
            ->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('viewing_slots', ['id' => $booking->slot_id, 'status' => 'taken']);
    }

    public function test_double_booking_is_rejected(): void
    {
        $slot = $this->futureSlot($this->availableProperty());
        $first = \App\Models\ViewingRequest::create([
            'property_id' => $slot->property_id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);
        $secondTenant = $this->newTenant();
        $second = \App\Models\ViewingRequest::create([
            'property_id' => $slot->property_id,
            'tenant_id' => $secondTenant->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);

        $this->actingAs($this->owner())->post('/owner/viewings/'.$first->id.'/accept')->assertRedirect();

        $this->actingAs($this->owner())->post('/owner/viewings/'.$second->id.'/accept')->assertStatus(409);

        $this->assertDatabaseHas('viewing_slots', ['id' => $slot->id, 'status' => 'taken']);
    }

    public function test_owner_can_decline_a_request(): void
    {
        $booking = $this->requestedBooking();

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/decline')->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'declined']);
        $this->assertDatabaseHas('viewing_slots', ['id' => $booking->slot_id, 'status' => 'available']);
    }

    public function test_reschedule_proposes_another_slot_then_tenant_confirms(): void
    {
        $booking = $this->requestedBooking();
        $newSlot = $this->futureSlot($booking->property, 7);

        $this->actingAs($this->owner())
            ->post('/owner/viewings/'.$booking->id.'/reschedule', ['slot_id' => $newSlot->id])
            ->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', [
            'id' => $booking->id,
            'slot_id' => $newSlot->id,
            'status' => 'rescheduled',
        ]);
        $this->assertDatabaseHas('viewing_slots', ['id' => $newSlot->id, 'status' => 'available']);

        $this->actingAs($this->tenant())
            ->post('/tenant/viewings/'.$booking->id.'/confirm')
            ->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('viewing_slots', ['id' => $newSlot->id, 'status' => 'taken']);
    }

    public function test_cancel_releases_a_locked_slot(): void
    {
        $booking = $this->requestedBooking();
        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/accept')->assertRedirect();

        $this->actingAs($this->tenant())
            ->post('/tenant/viewings/'.$booking->id.'/cancel')
            ->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('viewing_slots', ['id' => $booking->slot_id, 'status' => 'available']);
    }

    public function test_owner_can_mark_completed_and_no_show(): void
    {
        $booking = $this->requestedBooking();
        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/accept')->assertRedirect();

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'completed']);

        $another = $this->requestedBooking();
        $this->actingAs($this->owner())->post('/owner/viewings/'.$another->id.'/accept')->assertRedirect();
        $this->actingAs($this->owner())->post('/owner/viewings/'.$another->id.'/no-show')->assertRedirect();

        $this->assertDatabaseHas('viewing_requests', ['id' => $another->id, 'status' => 'no-show']);
        $this->assertDatabaseHas('viewing_slots', ['id' => $another->slot_id, 'status' => 'available']);
    }

    public function test_invalid_state_transitions_are_rejected(): void
    {
        $booking = $this->requestedBooking();

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/complete')->assertStatus(409);
        $this->assertDatabaseHas('viewing_requests', ['id' => $booking->id, 'status' => 'requested']);

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/accept')->assertRedirect();
        $this->actingAs($this->tenant())->post('/tenant/viewings/'.$booking->id.'/confirm')->assertStatus(409);
    }

    public function test_owner_cannot_act_on_another_owners_booking(): void
    {
        \App\Models\ViewingRequest::query()->delete();
        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 600,
            'status' => 'available',
            'city' => 'Harare',
        ]);
        $slot = $this->futureSlot($otherProperty);
        $booking = \App\Models\ViewingRequest::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/accept')->assertNotFound();
        $this->actingAs($this->owner())->get('/owner/viewings')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Viewings/Index')->has('requests', 0));
    }

    public function test_tenant_cannot_touch_another_tenants_booking(): void
    {
        $booking = $this->requestedBooking();
        $otherTenant = $this->newTenant();

        $this->actingAs($otherTenant)->post('/tenant/viewings/'.$booking->id.'/cancel')->assertNotFound();
        $this->actingAs($otherTenant)->post('/tenant/viewings/'.$booking->id.'/confirm')->assertNotFound();
    }

    public function test_owner_requests_page_lists_only_own(): void
    {
        \App\Models\ViewingRequest::query()->delete();
        $otherOwner = $this->newOwner();
        $otherProperty = Property::create([
            'owner_id' => $otherOwner->id,
            'title' => 'Foreign House',
            'property_type' => 'house',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 600,
            'status' => 'available',
            'city' => 'Harare',
        ]);
        $slot = $this->futureSlot($otherProperty);
        \App\Models\ViewingRequest::create([
            'property_id' => $otherProperty->id,
            'tenant_id' => $this->tenant()->id,
            'slot_id' => $slot->id,
            'status' => 'requested',
        ]);

        $this->actingAs($otherOwner)->get('/owner/viewings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Viewings/Index')
                ->has('requests', 1)
                ->where('requests.0.property_id', $otherProperty->id));

        $this->actingAs($this->owner())->get('/owner/viewings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Viewings/Index')->has('requests', 0));
    }

    public function test_owner_gets_notified_when_tenant_enquires(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $property = $this->availableProperty();
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => 'Hello there']);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->owner(),
            \App\Notifications\NewEnquiryNotification::class,
        );
    }

    public function test_tenant_gets_notified_when_owner_replies(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Is it available?',
            'status' => 'new',
        ]);

        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Yes!'])
            ->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\EnquiryRepliedNotification::class,
        );
    }

    public function test_owner_gets_notified_when_tenant_requests_viewing(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $property = $this->availableProperty();
        $slot = $this->futureSlot($property);

        $this->actingAs($this->tenant())->post('/tenant/viewings', [
            'property_id' => $property->id,
            'slot_id' => $slot->id,
        ])->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->owner(),
            \App\Notifications\ViewingRequestedNotification::class,
        );
    }

    public function test_tenant_gets_notified_when_viewing_accepted(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $booking = $this->requestedBooking();

        $this->actingAs($this->owner())->post('/owner/viewings/'.$booking->id.'/accept')->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\ViewingAcceptedNotification::class,
        );
    }

    public function test_tenant_gets_notified_when_viewing_rescheduled(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $booking = $this->requestedBooking();
        $newSlot = $this->futureSlot($booking->property, 7);

        $this->actingAs($this->owner())
            ->post('/owner/viewings/'.$booking->id.'/reschedule', ['slot_id' => $newSlot->id])
            ->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->tenant(),
            \App\Notifications\ViewingRescheduledNotification::class,
        );
    }

    public function test_notifications_page_lists_own_with_unread_count(): void
    {
        \Illuminate\Notifications\DatabaseNotification::query()->delete();

        $property = $this->availableProperty();
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => 'Hello there'])
            ->assertRedirect();

        $this->actingAs($this->owner())->get('/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications', 1)
                ->where('unreadCount', 1)
                ->where('notifications.0.data.title', 'New enquiry about '.$property->title));
    }

    public function test_read_marks_read_and_deep_links(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hello there',
            'status' => 'new',
        ]);
        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Sure'])
            ->assertRedirect();

        $notification = $this->tenant()->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $this->actingAs($this->tenant())->post('/notifications/'.$notification->id.'/read')
            ->assertRedirect(route('tenant.enquiries.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_read_another_users_notification(): void
    {
        $property = $this->availableProperty();
        $enquiry = Enquiry::create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant()->id,
            'message' => 'Hello there',
            'status' => 'new',
        ]);
        $this->actingAs($this->owner())->post('/owner/enquiries/'.$enquiry->id.'/reply', ['reply' => 'Sure'])
            ->assertRedirect();

        $notification = $this->tenant()->notifications()->latest()->first();

        $this->actingAs($this->owner())
            ->post('/notifications/'.$notification->id.'/read')
            ->assertNotFound();
    }

    public function test_read_all_marks_everything_read(): void
    {
        $property = $this->availableProperty();
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => 'First']);
        $this->actingAs($this->tenant())->post('/tenant/enquiries/'.$property->id, ['message' => 'Second']);

        $this->actingAs($this->owner())->post('/notifications/read-all')->assertRedirectBack();

        $this->assertSame(0, $this->owner()->unreadNotifications()->count());
    }

    public function test_guest_is_redirected_to_login_for_notifications(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }
}