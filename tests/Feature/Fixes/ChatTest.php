<?php

namespace Tests\Feature\Fixes;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

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

    private function chat(): ChatService
    {
        return app(ChatService::class);
    }

    private function freshProperty(): Property
    {
        return Property::create([
            'owner_id' => $this->owner()->id,
            'title' => 'Chat Test Cottage',
            'property_type' => 'cottage',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'price' => 450.00,
            'status' => 'available',
            'suburb' => 'Avondale',
            'city' => 'Harare',
        ]);
    }

    private function stranger(string $role = 'Tenant'): User
    {
        $user = User::factory()->create(['name' => 'Stranger', 'password_changed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        return $user;
    }

    // ---------- direct conversations ----------

    public function test_starting_a_direct_chat_creates_one_conversation_with_tenant_and_owner(): void
    {
        $property = $this->freshProperty();

        $this->actingAs($this->tenant())
            ->post(route('chat.start-direct', $property->id))
            ->assertRedirect();

        $conversations = Conversation::where('type', 'direct')->where('property_id', $property->id)->get();
        $this->assertCount(1, $conversations);

        $participantIds = $conversations->first()->participants->pluck('id')->all();
        $this->assertContains($this->tenant()->id, $participantIds);
        $this->assertContains($this->owner()->id, $participantIds);
    }

    public function test_starting_a_direct_chat_twice_returns_the_same_conversation(): void
    {
        $property = $this->freshProperty();

        $first = $this->chat()->startDirect($this->tenant(), $property);
        $second = $this->chat()->startDirect($this->tenant(), $property);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Conversation::where('property_id', $property->id)->count());
    }

    public function test_direct_chat_requires_the_property_to_have_an_owner(): void
    {
        $property = new Property(['owner_id' => null]);

        $this->expectException(ValidationException::class);
        $this->chat()->startDirect($this->tenant(), $property);
    }

    // ---------- posting + participation ----------

    public function test_a_participant_can_post_a_message(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());

        $this->actingAs($this->tenant())
            ->post(route('chat.message', $conversation->id), ['body' => 'Hello there'])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->tenant()->id,
            'body' => 'Hello there',
        ]);
    }

    public function test_a_non_participant_cannot_post_to_a_conversation(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());

        $this->actingAs($this->stranger())
            ->post(route('chat.message', $conversation->id), ['body' => 'Let me in'])
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_messages', ['conversation_id' => $conversation->id, 'body' => 'Let me in']);
    }

    public function test_message_body_is_required_and_capped(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());

        $this->actingAs($this->tenant())
            ->post(route('chat.message', $conversation->id), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->tenant())
            ->post(route('chat.message', $conversation->id), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }

    // ---------- support conversations ----------

    public function test_support_chat_reaches_admins_who_can_reply(): void
    {
        $conversation = $this->chat()->startSupport($this->tenant());
        $this->chat()->postMessage($conversation, $this->tenant(), 'I need help');

        // Admin can open and read the thread even before joining it.
        $this->actingAs($this->admin())
            ->get(route('admin.support.show', $conversation->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Support/Index'));

        $this->actingAs($this->admin())
            ->post(route('admin.support.reply', $conversation->id), ['body' => 'Happy to help'])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->admin()->id,
            'body' => 'Happy to help',
        ]);
        // The agent is now a participant of the thread.
        $this->assertTrue($conversation->fresh()->hasParticipant($this->admin()));
    }

    public function test_a_user_cannot_read_another_users_support_thread(): void
    {
        $conversation = $this->chat()->startSupport($this->tenant());

        $this->actingAs($this->stranger())
            ->get(route('chat.show', $conversation->id))
            ->assertNotFound();
    }

    public function test_a_user_cannot_read_a_direct_thread_they_are_not_in(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());

        $this->actingAs($this->stranger())
            ->get(route('chat.show', $conversation->id))
            ->assertNotFound();
    }

    // ---------- unread counts ----------

    public function test_unread_count_tracks_new_messages_and_mark_read_clears_it(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());

        // Owner writes; the tenant now has one unread message.
        $this->chat()->postMessage($conversation, $this->owner(), 'Are you still interested?');

        $this->assertSame(1, $this->chat()->unreadTotal($this->tenant()));
        // The sender never counts their own message as unread.
        $this->assertSame(0, $this->chat()->unreadTotal($this->owner()));

        // Opening the thread marks it read.
        $this->actingAs($this->tenant())->get(route('chat.show', $conversation->id))->assertOk();

        $this->assertSame(0, $this->chat()->unreadTotal($this->tenant()->fresh()));
    }

    public function test_unread_endpoint_returns_count_and_conversations(): void
    {
        $conversation = $this->chat()->startDirect($this->tenant(), $this->freshProperty());
        $this->chat()->postMessage($conversation, $this->owner(), 'Ping');

        $this->actingAs($this->tenant())
            ->getJson(route('chat.unread'))
            ->assertOk()
            ->assertJsonStructure(['count', 'conversations']);
    }

    // ---------- access control (mirrors DzimbaAccessControlTest expectations) ----------

    public function test_guests_are_redirected_from_chat_routes(): void
    {
        $this->get(route('chat.index'))->assertRedirect(route('login'));
        $this->get(route('chat.show', 1))->assertRedirect(route('login'));
        $this->post(route('chat.message', 1), ['body' => 'hi'])->assertRedirect(route('login'));
        $this->post(route('chat.start-support'))->assertRedirect(route('login'));
    }

    public function test_any_authenticated_role_can_open_the_chat_page(): void
    {
        foreach (['owner@dzimba.local', 'tenant@dzimba.local', 'admin@system.local'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route('chat.index'))
                ->assertOk();
        }
    }

    public function test_only_admins_can_reach_the_support_inbox(): void
    {
        $this->get(route('admin.support.index'))->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get(route('admin.support.index'))->assertOk();
        $this->actingAs($this->owner())->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($this->tenant())->get(route('admin.support.index'))->assertForbidden();
    }
}
