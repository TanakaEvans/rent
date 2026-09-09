<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquiryRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(public Enquiry $enquiry)
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
            'title' => 'Owner replied about '.$this->enquiry->property?->title,
            'body' => $this->enquiry->reply,
            'link' => route('tenant.enquiries.index'),
        ];
    }
}