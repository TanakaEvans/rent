<?php

namespace App\Notifications;

use App\Models\RentInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentInvoiceDueNotification extends Notification
{
    use Queueable;

    public function __construct(public RentInvoice $invoice)
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
            'title' => 'Rent invoice due',
            'body' => $this->invoice->invoice_no.' — $'.$this->invoice->amount.' is due for '
                .$this->invoice->period_start->format('d M').' → '
                .$this->invoice->period_end->format('d M y'),
            'link' => route('tenant.rent.index'),
        ];
    }
}