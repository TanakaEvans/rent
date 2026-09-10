<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListingExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property, public int $daysLeft)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $on = $this->daysLeft === 1 ? 'tomorrow' : 'in '.$this->daysLeft.' days';

        return [
            'title' => 'Listing expiring '.$on,
            'body' => '"'.$this->property->title.'" expires '.$on.'. Renew it to keep it on the marketplace.',
            'link' => route('owner.properties.show', $this->property->id),
        ];
    }
}