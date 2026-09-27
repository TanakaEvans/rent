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
     * Post a message to a conversation.
     */
    public function message(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->chat->postMessage($conversation, $request->user(), $validated['body']);

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
     * Start (or reuse) the user's support chat and open it.
     */
    public function startSupport(Request $request)
    {
        $conversation = $this->chat->startSupport($request->user());

        return redirect()->route('chat.show', $conversation->id);
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
