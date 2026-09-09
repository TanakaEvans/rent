<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingAcceptedNotification extends Notification
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
            'title' => 'Viewing confirmed for '.$this->booking->property?->title,
            'body' => 'Your slot on '.$this->booking->slot?->starts_at?->format('D M j, H:i').' is locked in.',
            'link' => route('tenant.viewings.index'),
        ];
    }
}