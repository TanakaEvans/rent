<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Keeps the reporting tenant informed as their maintenance request moves:
 * a contractor was assigned (`assigned`) or has started work (`started`).
 */
class MaintenanceTenantUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request, public string $stage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $contractor = $this->request->contractor->business_name ?? 'A contractor';
        $subject = $this->request->request_no.' — '.$this->request->title;

        return $this->stage === 'started'
            ? [
                'title' => 'Repair work has started',
                'body' => $subject.': '.$contractor.' has started work on your request.',
                'link' => route('tenant.maintenance.index'),
            ]
            : [
                'title' => 'Contractor assigned to your request',
                'body' => $subject.': '.$contractor.' has been assigned and will be in touch to arrange access.',
                'link' => route('tenant.maintenance.index'),
            ];
    }
}
