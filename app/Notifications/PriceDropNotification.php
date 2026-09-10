<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriceDropNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Property $property,
        public float $oldAmount,
        public float $newAmount,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Price dropped on '.$this->property->title,
            'body' => 'Rent is now $'.number_format($this->newAmount, 2).' (was $'.number_format($this->oldAmount, 2).') — a listing you saved.',
            'link' => route('property.show', $this->property->id),
        ];
    }
}