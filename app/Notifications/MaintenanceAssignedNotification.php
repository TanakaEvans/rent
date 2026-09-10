<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Job brief delivered to a contractor when an owner assigns them a
 * maintenance request (Module 11 FR-03, Module 10 AC-02).
 */
class MaintenanceAssignedNotification extends Notification
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
            'title' => 'New maintenance job',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property')
                .' ('.ucfirst($this->request->priority).' priority).'
                .'$'.number_format((float) $this->request->approved_quote, 2).' quoted.',
            'link' => route('contractor.maintenance.index'),
        ];
    }
}