<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Property;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    /**
     * The signed-in user's conversations, with one opened when asked.
     */
    public function index(Request $request)
    {
        $conversations = $this->chat->listFor($request->user());

        $active = null;
        if ($request->filled('conversation')) {
            $selected = $conversations->firstWhere('id', (int) $request->query('conversation'));
            if ($selected) {
                $active = $this->chat->openFor($selected, $request->user());
            }
        }

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
        ]);
    }

    /**
     * Open a single conversation (marks it read for the viewer).
     */
    public function show(Request $request, Conversation $conversation)
    {
        $active = $this->chat->openFor($conversation, $request->user());

        return Inertia::render('Chat/Index', [
            'conversations' => $this->chat->listFor($request->user()),
            'activeConversation' => $active,
        ]);
    }

    /**
     * A single conversation's thread as JSON, for the inline chat widget.
     * Opening it advances the viewer's read cursor (same as the full page).
     */
    public function thread(Request $request, Conversation $conversation)
    {
        $active = $this->chat->openFor($conversation, $request->user());
        $userId = $request->user()->id;

        return response()->json([
            'id' => $active->id,
            'type' => $active->type,
            'peer' => $this->peerName($active, $userId),
            'property_title' => $active->property?->title,
            'messages' => $active->messages
                ->sortBy('created_at')
                ->map(fn ($m) => $this->presentMessage($m, $userId))
                ->values(),
        ]);
    }

    /**
     * Post a message to a conversation. Returns JSON (the new message) for the
     * inline widget, or redirects back for the full-page form.
     */
    public function message(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $this->chat->postMessage($conversation, $request->user(), $validated['body']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $this->presentMessage($message->loadMissing('sender:id,name'), $request->user()->id),
            ]);
        }

        return redirect()->back()->with('success', 'Message sent.');
    }

    /**
     * Start (or reuse) a direct chat with a property's owner and open it.
     */
    public function startDirect(Request $request, Property $property)
    {
        $conversation = $this->chat->startDirect($request->user(), $property);

        return redirect()->route('chat.show', $conversation->id);
    }

    /**
     * Start (or reuse) the user's support chat and open it. Returns the
     * conversation id as JSON for the inline widget, else opens the page.
     */
    public function startSupport(Request $request)
    {
        $conversation = $this->chat->startSupport($request->user());

        if ($request->wantsJson()) {
            return response()->json(['id' => $conversation->id]);
        }

        return redirect()->route('chat.show', $conversation->id);
    }

    /**
     * The display name of the other side of a conversation.
     */
    private function peerName(Conversation $conversation, int $userId): string
    {
        if ($conversation->type === Conversation::TYPE_SUPPORT) {
            return 'ZimRent Support';
        }

        return $conversation->participants->firstWhere('id', '!=', $userId)?->name ?? 'ZimRent user';
    }

    /**
     * Shape one chat message for the widget.
     *
     * @return array{id: int, body: string, mine: bool, sender: string, at: string}
     */
    private function presentMessage(\App\Models\ChatMessage $message, int $userId): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender_id === $userId,
            'sender' => $message->sender?->name ?? 'ZimRent user',
            'at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Lightweight unread total plus a compact conversation list for the
     * floating widget and the header badge (polled over normal requests).
     */
    public function unread(Request $request)
    {
        $user = $request->user();

        $conversations = $this->chat->listFor($user)->take(8)->map(fn ($conversation) => [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'subject' => $conversation->subject,
            'unread_count' => $conversation->unread_count,
            'property_title' => $conversation->property?->title,
            'preview' => $conversation->latestMessage?->body,
            'participants' => $conversation->participants->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
        ])->values();

        return response()->json([
            'count' => $this->chat->unreadTotal($user),
            'conversations' => $conversations,
        ]);
    }
}
