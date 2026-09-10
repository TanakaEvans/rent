<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EmergencyMaintenanceNotification extends Notification
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
        return [
            'title' => 'Emergency maintenance request',
            'body' => $this->request->title.' ('.ucfirst($this->request->category).') on '
                .($this->request->property->title ?? 'a property')
                .' flagged as EMERGENCY — confirm a first response starts within the SLA.',
            'link' => route('admin.maintenance.escalations.index'),
        ];
    }
}