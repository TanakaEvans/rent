<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquiryTenantRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(public Enquiry $enquiry, public string $body)
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
            'title' => ($this->enquiry->tenant?->name ?? 'A tenant').' replied about '.$this->enquiry->property?->title,
            'body' => $this->body,
            'link' => route('owner.enquiries.show', $this->enquiry->id),
        ];
    }
}
