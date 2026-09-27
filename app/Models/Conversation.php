<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    /**
     * The two kinds of chat thread.
     */
    public const TYPE_DIRECT = 'direct';

    public const TYPE_SUPPORT = 'support';

    public const TYPES = [
        self::TYPE_DIRECT => 'Direct',
        self::TYPE_SUPPORT => 'Support',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'property_id',
        'subject',
    ];

    /**
     * The property a direct conversation is about (null for support threads).
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The users taking part in the conversation. `last_read_at` on the pivot
     * is the per-participant read cursor.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user', 'conversation_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * The messages on the thread, oldest first.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->oldest()->orderBy('id');
    }

    /**
     * The most recent message, for list previews (serialized as an object).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany('id');
    }

    /**
     * Whether the given user takes part in this conversation.
     */
    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $user->id);
        }

        return $this->participants()->where('auth_users.id', $user->id)->exists();
    }

    /**
     * Count of messages the given user has not yet read (posted by someone
     * else after their read cursor). A user who is not a participant has none.
     */
    public function unreadCountFor(User $user): int
    {
        $pivot = $this->participants()->where('auth_users.id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($pivot->last_read_at, fn ($query) => $query->where('created_at', '>', $pivot->last_read_at))
            ->count();
    }

    /**
     * Advance the given participant's read cursor to now.
     */
    public function markRead(User $user): void
    {
        $this->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }
}
