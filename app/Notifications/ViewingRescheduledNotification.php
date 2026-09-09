<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingRescheduledNotification extends Notification
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
            'title' => 'New time proposed for '.$this->booking->property?->title,
            'body' => 'The owner suggested '.$this->booking->slot?->starts_at?->format('D M j, H:i').' — confirm or cancel.',
            'link' => route('tenant.viewings.index'),
        ];
    }
}