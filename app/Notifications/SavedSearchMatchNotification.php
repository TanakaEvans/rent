<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SavedSearchMatchNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property, public SavedSearch $search)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New match for "'.$this->search->name.'"',
            'body' => $this->property->title.' in '.($this->property->suburb ?: $this->property->city).' matches your saved search.',
            'link' => route('property.show', $this->property->id),
        ];
    }
}