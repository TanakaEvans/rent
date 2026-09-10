<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The owner closed the request after tenant confirmation. Reaches the tenant
 * and the assigned contractor; the request now feeds maintenance spend.
 */
class MaintenanceClosedNotification extends Notification
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

        return [
            'title' => 'Maintenance request closed',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property').' is closed.'
                .' Approved quote '.'$'.number_format((float) $this->request->approved_quote, 2).'.',
            'link' => $this->request->tenant_id === (int) $notifiable->id
                ? route('tenant.maintenance.index')
                : route('contractor.maintenance.index'),
        ];
    }
}