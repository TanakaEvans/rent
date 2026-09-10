<?php

namespace App\Notifications;

use App\Models\AdPlacement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdPlacementCancelledNotification extends Notification
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
        $credit = (float) $this->placement->credit_amount;

        return [
            'title' => 'Listing promotion cancelled',
            'body' => 'The promotion for '.$this->placement->property->title.' was cancelled'
                .($credit > 0 ? ' with a '.number_format($credit, 2).' credit.' : '.'),
            'link' => route('owner.advertising.index'),
        ];
    }
}