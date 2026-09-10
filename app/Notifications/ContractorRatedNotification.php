<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The property owner scored the contractor's closed job (Module 11, FR-04).
 * Reaches the linked contractor login so they see the feedback instantly.
 */
class ContractorRatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MaintenanceRequest $request,
        public int $score,
        public ?string $note = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $property = $this->request->property;
        $hasNote = $this->note !== null && $this->note !== '';

        return [
            'title' => 'New rating on your job',
            'body' => $this->request->request_no.' — '.$this->request->title
                .' at '.($property->title ?? 'a property').' was rated '.$this->score.'/5'
                .($hasNote ? ' ('.$this->note.')' : '').'.',
            'link' => route('contractor.maintenance.index'),
        ];
    }
}