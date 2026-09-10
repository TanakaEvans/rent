<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMaintenanceRequestNotification extends Notification
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
            'title' => 'New maintenance request',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' on '.($this->request->property->title ?? 'a property')
                .' ('.ucfirst($this->request->priority).' priority).',
            'link' => route('owner.maintenance.index'),
        ];
    }
}