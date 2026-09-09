<?php

namespace App\Notifications;

use App\Models\RentalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewApplicationNotification extends Notification
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
            'title' => 'New application for '.$this->application->property?->title,
            'body' => $this->application->applicant?->name,
            'link' => route('owner.applications.index'),
        ];
    }
}