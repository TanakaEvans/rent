<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner when the tenant confirms a rescheduled viewing time.
 */
class ViewingConfirmedNotification extends Notification
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
            'title' => 'New viewing time confirmed for '.$this->booking->property?->title,
            'body' => ($this->booking->tenant?->name ?? 'The tenant').' confirmed '.$this->booking->slot?->starts_at?->format('D M j, H:i').'. The slot is now locked.',
            'link' => route('owner.viewings.index'),
        ];
    }
}
