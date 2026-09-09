<?php

namespace App\Notifications;

use App\Models\RentalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public RentalApplication $application)
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
        return [
            'title' => 'Your application was not approved',
            'body' => $this->application->property?->title,
            'link' => route('tenant.applications.index'),
        ];
    }
}