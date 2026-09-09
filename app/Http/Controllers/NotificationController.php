<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * The user's in-app inbox, newest first.
     */
    public function index(Request $request)
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => $request->user()->notifications()->latest()->get(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification read and deep-link to its source screen.
     */
    public function read(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->getKey(), 404);

        $notification->markAsRead();

        return redirect($notification->data['link'] ?? route('notifications.index'));
    }

    /**
     * Mark every notification of the signed-in user as read.
     */
    public function readAll(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}