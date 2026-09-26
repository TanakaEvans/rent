<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingDeclinedNotification extends Notification
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
            'title' => 'Viewing declined for '.$this->booking->property?->title,
            'body' => 'The owner could not host the viewing on '.$this->booking->slot?->starts_at?->format('D M j, H:i').'. You can request another time from the listing.',
            'link' => route('tenant.viewings.index'),
        ];
    }
}
