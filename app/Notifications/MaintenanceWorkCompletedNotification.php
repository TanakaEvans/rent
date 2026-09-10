<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The contractor reports the fix done. Lands with the owner (who awaits the
 * tenant's confirmation) and the tenant (who should confirm before close).
 */
class MaintenanceWorkCompletedNotification extends Notification
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
        $contractor = $this->request->contractor;

        return [
            'title' => 'Maintenance work completed',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property')
                .' has been fixed by '.($contractor->business_name ?? 'the assigned contractor')
                .'. Please confirm the repair so the request can be closed.',
            'link' => $this->request->tenant_id === (int) $notifiable->id
                ? route('tenant.maintenance.index')
                : route('owner.maintenance.index'),
        ];
    }
}