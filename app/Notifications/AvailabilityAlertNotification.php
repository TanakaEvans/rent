<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvailabilityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Back on the market: '.$this->property->title,
            'body' => 'A listing you saved is available again in '.($this->property->suburb ?: $this->property->city).'.',
            'link' => route('property.show', $this->property->id),
        ];
    }
}