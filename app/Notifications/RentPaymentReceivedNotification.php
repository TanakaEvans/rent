<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentPaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment)
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
     * The payload rendered in the property owner's inbox once a tenant's rent
     * payment settles.
     */
    public function toDatabase(object $notifiable): array
    {
        $invoice = $this->payment->invoice;
        $currency = $invoice->property?->currency === 'ZWL' ? 'ZWL ' : '$';

        return [
            'title' => 'Rent payment received',
            'body' => $currency.$this->payment->amount.' received for invoice '.$invoice->invoice_no
                .' on '.($invoice->property?->title ?? 'your property')
                .' (receipt '.$this->payment->receipt_no.').',
            'link' => route('owner.rent.index'),
        ];
    }
}
