<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService
{
    /**
     * Roles that may join and read every support conversation.
     */
    private const SUPPORT_ROLES = ['Admin', 'Superuser'];

    /**
     * Open (or reuse) the single direct conversation between a tenant and the
     * owner of a property. There is exactly one direct thread per
     * (tenant, owner, property) triple. The property must have an owner.
     */
    public function startDirect(User $tenant, Property $property): Conversation
    {
        if (! $property->owner_id) {
            throw ValidationException::withMessages([
                'chat' => ['This property has no owner to message yet.'],
            ]);
        }

        if ($property->owner_id === $tenant->id) {
            throw ValidationException::withMessages([
                'chat' => ['You cannot start a conversation about your own listing.'],
            ]);
        }

        $existing = Conversation::query()
            ->where('type', Conversation::TYPE_DIRECT)
            ->where('property_id', $property->id)
            ->whereHas('participants', fn ($query) => $query->where('auth_users.id', $tenant->id))
            ->whereHas('participants', fn ($query) => $query->where('auth_users.id', $property->owner_id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($tenant, $property) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_DIRECT,
                'property_id' => $property->id,
                'subject' => $property->title,
            ]);

            $conversation->participants()->attach([$tenant->id, $property->owner_id]);

            return $conversation;
        });
    }

    /**
     * Open (or reuse) the single support conversation for a user. Support
     * staff are attached lazily when they first reply (see postMessage).
     */
    public function startSupport(User $user): Conversation
    {
        $existing = Conversation::query()
            ->where('type', Conversation::TYPE_SUPPORT)
            ->whereHas('participants', fn ($query) => $query->where('auth_users.id', $user->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_SUPPORT,
                'subject' => 'ZimRent Support',
            ]);

            $conversation->participants()->attach($user->id);

            return $conversation;
        });
    }

    /**
     * Post a message to a conversation. The sender must take part in it —
     * except that any support agent (Admin/Superuser) may post to a support
     * conversation and is joined to it on their first reply.
     */
    public function postMessage(Conversation $conversation, User $sender, string $body): ChatMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['Type a message before sending.'],
            ]);
        }

        if (mb_strlen($body) > 2000) {
            throw ValidationException::withMessages([
                'body' => ['A message cannot be longer than 2000 characters.'],
            ]);
        }

        if (! $this->canParticipate($conversation, $sender)) {
            abort(403, 'You cannot post to this conversation.');
        }

        return DB::transaction(function () use ($conversation, $sender, $body) {
            if (! $conversation->hasParticipant($sender)) {
                // A support agent replying for the first time joins the thread.
                $conversation->participants()->attach($sender->id);
            }

            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->touch();
            $conversation->markRead($sender);

            return $message;
        });
    }

    /**
     * Conversations the user takes part in, most recently active first, with
     * the peer, property and last message loaded for the list view.
     */
    public function listFor(User $user): Collection
    {
        return Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('auth_users.id', $user->id))
            ->with([
                'property:id,title,cover_image,suburb,city',
                'participants:id,name',
                'latestMessage',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->each(fn (Conversation $conversation) => $conversation->unread_count = $conversation->unreadCountFor($user));
    }

    /**
     * All support conversations for the staff inbox, most recently active
     * first, with the requesting user and last message loaded.
     */
    public function supportInbox(): Collection
    {
        return Conversation::query()
            ->where('type', Conversation::TYPE_SUPPORT)
            ->with(['participants:id,name,email', 'latestMessage'])
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Load a conversation for reading by the given user, enforcing access,
     * and advance their read cursor. A support agent may read any support
     * thread even before joining it.
     */
    public function openFor(Conversation $conversation, User $user): Conversation
    {
        if (! $this->canParticipate($conversation, $user)) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $conversation->load([
            'property:id,title,cover_image,suburb,city,status',
            'participants:id,name',
            'messages.sender:id,name',
        ]);

        if ($conversation->hasParticipant($user)) {
            $conversation->markRead($user);
        }

        return $conversation;
    }

    /**
     * The user's total unread message count across every conversation.
     */
    public function unreadTotal(User $user): int
    {
        return (int) DB::table('chat_messages')
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'chat_messages.conversation_id')
            ->where('conversation_user.user_id', $user->id)
            ->where('chat_messages.sender_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'conversation_user.last_read_at');
            })
            ->count();
    }

    /**
     * Whether the user may read/post in the conversation: a participant, or a
     * support agent on a support conversation.
     */
    private function canParticipate(Conversation $conversation, User $user): bool
    {
        if ($conversation->hasParticipant($user)) {
            return true;
        }

        return $conversation->type === Conversation::TYPE_SUPPORT
            && $user->hasAnyRole(self::SUPPORT_ROLES);
    }
}
