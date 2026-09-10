<?php

namespace App\Notifications;

use App\Models\RentInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentInvoiceOverdueNotification extends Notification
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
     * The payload rendered in the in-app inbox. The deep-link resolves to the
     * correct party's rent page — tenants land on their own invoices, owners on
     * the rent & income ledger.
     */
    public function toDatabase(object $notifiable): array
    {
        $isTenant = $notifiable->id === $this->invoice->tenant_id;

        return [
            'title' => 'Rent invoice overdue',
            'body' => $this->invoice->invoice_no.' — $'.$this->invoice->amount.' for '
                .$this->invoice->period_start->format('d M').' → '
                .$this->invoice->period_end->format('d M y')
                .' is now overdue'
                .($isTenant ? '. Please pay to avoid a late charge.' : '.'),
            'link' => $isTenant ? route('tenant.rent.index') : route('owner.rent.index'),
        ];
    }
}