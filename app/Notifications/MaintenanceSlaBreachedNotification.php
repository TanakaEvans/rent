<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceSlaBreachedNotification extends Notification
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
            'title' => 'Maintenance SLA breached',
            'body' => $this->request->request_no.' ('.ucfirst($this->request->priority).' priority) remains unactioned past its first-response SLA — '.$this->request->title.'.',
            'link' => route('admin.maintenance.escalations.index'),
        ];
    }
}