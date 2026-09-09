<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaseCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Lease $lease)
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
            'title' => 'Your lease is ready',
            'body' => $this->lease->lease_no.' — '.$this->lease->property?->title,
            'link' => route('tenant.leases.index'),
        ];
    }
}