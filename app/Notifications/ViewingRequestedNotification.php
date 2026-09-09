<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public ViewingRequest $booking)
    {
    }

    /**
     * In-app bell for the MVP.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload rendered in the in-app inbox.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New viewing request for '.$this->booking->property?->title,
            'body' => ($this->booking->tenant?->name ?? 'A tenant').' asked to view at '.$this->booking->slot?->starts_at?->format('D M j, H:i'),
            'link' => route('owner.viewings.index'),
        ];
    }
}