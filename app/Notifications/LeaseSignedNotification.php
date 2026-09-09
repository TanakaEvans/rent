<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaseSignedNotification extends Notification
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
            'title' => 'Lease now active',
            'body' => $this->lease->lease_no.' has been signed by both parties — the property is occupied.',
            'link' => $notifiable->getKey() === $this->lease->tenant_id
                ? route('tenant.leases.index')
                : route('owner.leases.index'),
        ];
    }
}