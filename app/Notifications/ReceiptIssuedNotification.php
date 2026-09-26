<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReceiptIssuedNotification extends Notification
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
     * The payload rendered in the in-app inbox.
     */
    public function toDatabase(object $notifiable): array
    {
        $currency = $this->payment->invoice->property?->currency === 'ZWL' ? 'ZWL ' : '$';

        return [
            'title' => 'Payment received',
            'body' => 'Receipt '.$this->payment->receipt_no.' — '.$currency.$this->payment->amount.' recorded for '
                .$this->payment->invoice->invoice_no,
            'link' => route('tenant.rent.index'),
        ];
    }
}