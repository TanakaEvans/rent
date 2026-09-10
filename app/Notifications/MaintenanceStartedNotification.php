<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Alerts the owner once an assigned contractor begins work on a job
 * (Module 10 workflow "Track").
 */
class MaintenanceStartedNotification extends Notification
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
            'title' => 'Maintenance work started',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property')
                .' is now being worked on by '.($contractor->business_name ?? 'the assigned contractor').'.',
            'link' => route('owner.maintenance.index'),
        ];
    }
}