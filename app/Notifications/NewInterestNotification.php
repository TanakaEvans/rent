<?php

namespace App\Notifications;

use App\Models\ExpressInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewInterestNotification extends Notification
{
    use Queueable;

    public function __construct(public ExpressInterest $interest)
    {
    }

    /**
     * In-app bell for the MVP. Fires on a new expression of interest and on
     * a re-express that re-opens an archived row — never on a no-op.
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
            'title' => 'New interest in '.$this->interest->property?->title,
            'body' => $this->interest->tenant?->name.' expressed interest in your listing.',
            'link' => route('owner.interests.index', ['property' => $this->interest->property_id]),
        ];
    }
}