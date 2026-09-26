<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public ?string $note = null)
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
     * The payload rendered in the in-app inbox: which invoice is still owing
     * and, when staff gave one, the reason the payment was rejected.
     */
    public function toDatabase(object $notifiable): array
    {
        $body = 'Your payment for invoice '.$this->payment->invoice->invoice_no
            .' was not accepted. The invoice is still owing — please pay again.';

        if ($this->note !== null && $this->note !== '') {
            $body .= ' Reason: '.$this->note;
        }

        return [
            'title' => 'Payment rejected',
            'body' => $body,
            'note' => $this->note,
            'link' => route('tenant.rent.index'),
        ];
    }
}
