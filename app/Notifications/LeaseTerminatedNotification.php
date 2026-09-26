<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to both parties when the owner ends an active lease.
 */
class LeaseTerminatedNotification extends Notification
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
        $toTenant = $notifiable->getKey() === $this->lease->tenant_id;

        return [
            'title' => 'Lease '.$this->lease->lease_no.' ended',
            'body' => 'The lease for '.$this->lease->property?->title.' ends on '.$this->lease->terminated_on?->format('d M Y').'. Reason: '.$this->lease->termination_reason,
            'link' => $toTenant ? route('tenant.leases.index') : route('owner.leases.index'),
        ];
    }
}
