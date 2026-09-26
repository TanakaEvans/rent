<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to the tenant when an Admin/Superuser decides on their
 * identity evidence (S5): approved, rejected or revoked.
 */
class KycDocumentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public string $status, public ?string $note = null)
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
        [$title, $body] = match ($this->status) {
            'approved' => ['Identity document approved', 'Your identity evidence was approved — your ZimRent badge tier is updated.'],
            'rejected' => ['Identity document rejected', $this->note ? 'Your identity evidence was rejected: '.$this->note : 'Your identity evidence was rejected — you can upload a clearer scan and try again.'],
            'revoked' => ['Identity document revoked', $this->note ? 'A previously approved identity document was revoked: '.$this->note : 'A previously approved identity document was revoked.'],
            default => ['Identity document update', 'Your identity evidence status changed.'],
        };

        return [
            'title' => $title,
            'body' => $body,
            'link' => route('tenant.profile'),
        ];
    }
}