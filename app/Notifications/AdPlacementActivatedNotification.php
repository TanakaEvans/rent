<?php

namespace App\Notifications;

use App\Models\AdPlacement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdPlacementActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdPlacement $placement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Listing promotion active',
            'body' => $this->placement->property->title
                .' is now promoted until '.$this->placement->ends_at->format('d M y')
                .'.',
            'link' => route('owner.advertising.index'),
        ];
    }
}