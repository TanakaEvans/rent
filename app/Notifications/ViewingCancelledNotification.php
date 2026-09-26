<?php

namespace App\Notifications;

use App\Models\ViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the other party when the owner or the tenant cancels a viewing.
 */
class ViewingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(public ViewingRequest $booking, public string $cancelledBy)
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
        $toTenant = $notifiable->getKey() === $this->booking->tenant_id;

        return [
            'title' => 'Viewing cancelled for '.$this->booking->property?->title,
            'body' => 'The '.$this->cancelledBy.' cancelled the viewing on '.$this->booking->slot?->starts_at?->format('D M j, H:i').'.',
            'link' => $toTenant ? route('tenant.viewings.index') : route('owner.viewings.index'),
        ];
    }
}
