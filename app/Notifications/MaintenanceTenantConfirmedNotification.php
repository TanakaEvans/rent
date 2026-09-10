<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The tenant has confirmed the fix — the owner may now close the request
 * (Module 10 FR-06: tenant confirms, owner closes after inspection).
 */
class MaintenanceTenantConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $property = $this->request->property;
        $tenant = $this->request->tenant;

        return [
            'title' => 'Tenant confirmed the fix',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property')
                .' was confirmed by '.($tenant->name ?? 'the tenant').'. You can now close the request.',
            'link' => route('owner.maintenance.index'),
        ];
    }
}