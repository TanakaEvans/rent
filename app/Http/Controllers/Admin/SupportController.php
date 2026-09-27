<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportController extends Controller
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    /**
     * The staff inbox of every support conversation, one opened when asked.
     */
    public function index(Request $request)
    {
        $conversations = $this->chat->supportInbox();

        $active = null;
        if ($request->filled('conversation')) {
            $selected = $conversations->firstWhere('id', (int) $request->query('conversation'));
            if ($selected) {
                $active = $this->chat->openFor($selected, $request->user());
            }
        }

        return Inertia::render('Admin/Support/Index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
        ]);
    }

    /**
     * Open one support conversation for the staff reviewer.
     */
    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->type === Conversation::TYPE_SUPPORT, 404);

        $active = $this->chat->openFor($conversation, $request->user());

        return Inertia::render('Admin/Support/Index', [
            'conversations' => $this->chat->supportInbox(),
            'activeConversation' => $active,
        ]);
    }

    /**
     * Reply to a support conversation as staff (joins the thread on first reply).
     */
    public function reply(Request $request, Conversation $conversation)
    {
        if ($conversation->type !== Conversation::TYPE_SUPPORT) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->chat->postMessage($conversation, $request->user(), $validated['body']);

        return redirect()->back()->with('success', 'Reply sent.');
    }
}
