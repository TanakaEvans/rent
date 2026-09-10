<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RentInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rent payment settlement (Module 09, Wave 4 slice 4).
 *
 * A payment is raised against a `due`/`overdue` rent invoice. It settles the
 * invoice immediately (status `settled` + unique receipt number from the
 * `numbering.receipt.*` config) unless the money engine decides it needs
 * confirmation — bank/mobile payments carry a proof-of-payment, and any
 * payment at or above `payments.approval.threshold` waits for staff in the
 * admin approval queue. Every rule (methods, POP, approval threshold) reads
 * ConfigurationService (Module 24), never a code constant.
 */
final class PaymentService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Record a tenant payment against one of their invoices (FR-04). Exact
     * amount only, idempotent per invoice (AC-02/NFR-02), method must be an
     * enabled `payments.methods` value, and bank/mobile methods require a
     * proof-of-payment when `payments.pop.approval_required` is on.
     */
    public function recordPayment(User $tenant, RentInvoice $invoice, array $data): Payment
    {
        if ($invoice->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException())->setModel(RentInvoice::class, [$invoice->id]);
        }

        if (! in_array($invoice->status, ['due', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Only a due or overdue invoice can be paid.',
            ]);
        }

        if ($invoice->payments()->whereIn('status', ['pending', 'settled'])->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice already has a payment — you cannot pay it twice.',
            ]);
        }

        $method = (string) ($data['method'] ?? '');
        $amount = $this->normalise((string) ($data['amount'] ?? '0'));
        $reference = $data['reference'] ?? null;
        $popPath = $data['pop_path'] ?? null;

        $this->assertAvailableMethod($method);
        $this->assertExactAmount($invoice, $amount);

        $popRequired = $this->popRequired();
        if ($popRequired && in_array($method, ['bank', 'mobile'], true) && $amount > 0 && $popPath === null) {
            throw ValidationException::withMessages([
                'pop' => 'A proof of payment is required for '.$method.' payments.',
            ]);
        }

        $approvalNeeded = ($popRequired && in_array($method, ['bank', 'mobile'], true))
            || $this->cents($amount) >= $this->cents($this->threshold());

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'paid_by' => $tenant->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference ? substr($reference, 0, 255) : null,
            'pop_path' => $popPath,
            'status' => 'pending',
        ]);

        if (! $approvalNeeded) {
            $this->settle(null, $payment);
        }

        return $payment->fresh(['invoice.property:id,title']);
    }

    /**
     * Staff confirm a pending payment: the invoice is settled with a receipt
     * and the invoice moves to `paid` (FR-03/FR-04).
     */
    public function approve(User $staff, Payment $payment): Payment
    {
        $this->settle($staff, $payment);

        return $payment->fresh();
    }

    /**
     * Staff reject a pending payment (e.g. POP mismatch, bank marker). The
     * invoice stays owing and the tenant may pay again.
     */
    public function reject(User $staff, Payment $payment): Payment
    {
        $this->assertPending($payment);

        $payment->update([
            'status' => 'rejected',
            'received_by' => $staff->id,
        ]);

        return $payment->fresh();
    }

    /**
     * Mark the payment settled and issue a unique receipt (AC-03).
     */
    private function settle(?User $staff, Payment $payment): void
    {
        if (! $payment->canTransitionTo('settled')) {
            throw ValidationException::withMessages(['invoice' => 'This payment cannot be settled.']);
        }

        $invoice = $payment->invoice;
        if (! $invoice->canTransitionTo('paid')) {
            throw ValidationException::withMessages(['invoice' => 'This invoice can no longer be settled.']);
        }

        $payment->update([
            'status' => 'settled',
            'received_by' => $staff?->id,
            'receipt_no' => $this->nextReceiptNo(),
            'paid_at' => now(),
        ]);

        $invoice->update(['status' => 'paid']);

        $payment->paidBy->notify(new \App\Notifications\ReceiptIssuedNotification($payment->fresh(['invoice'])));
    }

    private function assertPending(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'Only a pending payment can be rejected.']);
        }
    }

    /**
     * Payment methods currently enabled by configuration.
     */
    public function enabledMethods(): array
    {
        $methods = $this->config->get('payments.methods', ['cash', 'bank', 'mobile', 'online']);

        return is_array($methods) ? array_values(array_map('strval', $methods)) : [];
    }

    /**
     * The payment rules the UI needs: enabled methods, whether bank/mobile
     * proof-of-payment uploads are demanded, and the approval threshold.
     */
    public function paymentRules(): array
    {
        return [
            'methods' => $this->enabledMethods(),
            'pop_required' => $this->popRequired(),
            'approval_threshold' => $this->threshold(),
        ];
    }

    /**
     * Pending payments waiting for staff (admin approval queue).
     */
    public function pendingForAdmin()
    {
        return Payment::where('status', 'pending')
            ->with([
                'invoice:id,invoice_no,amount,period_start,period_end,status',
                'invoice.property:id,title',
                'paidBy:id,name,email',
            ])
            ->latest()
            ->get();
    }

    /**
     * The most recent settled/rejected payments (admin audit trail).
     */
    public function recentForAdmin(int $limit = 10)
    {
        return Payment::where('status', '!=', 'pending')
            ->with([
                'invoice:id,invoice_no,period_start,period_end,amount,status',
                'invoice.property:id,title',
                'paidBy:id,name,email',
            ])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * A plain-text receipt render for downloading (AC-03, streams like the
     * agreement documents).
     */
    public function renderReceiptText(Payment $payment): string
    {
        $invoice = $payment->invoice;
        $property = $invoice->property;

        $lines = [
            'DZIMBA — RENT RECEIPT',
            '----------------------',
            'Receipt No:    '.$payment->receipt_no,
            'Invoice No:    '.$invoice->invoice_no,
            'Tenant:        '.$payment->paidBy->name,
            'Property:      '.($property->title ?? '—'),
            'Period:        '.$invoice->period_start->format('d M Y').' → '.$invoice->period_end->format('d M Y'),
            'Amount:        $'.$payment->amount,
            'Method:        '.ucfirst($payment->method),
        ];

        if ($payment->reference) {
            $lines[] = 'Reference:     '.$payment->reference;
        }

        $lines[] = 'Paid on:       '.$payment->paid_at?->format('d M Y H:i');
        $lines[] = 'Status:        Paid';

        return implode("\n", $lines)."\n";
    }

    private function assertAvailableMethod(string $method): void
    {
        if (! in_array($method, $this->enabledMethods(), true)) {
            throw ValidationException::withMessages([
                'method' => 'The selected payment method is not enabled.',
            ]);
        }
    }

    private function assertExactAmount(RentInvoice $invoice, string $amount): void
    {
        if ($amount !== $invoice->amount) {
            throw ValidationException::withMessages([
                'amount' => 'The payment must match the invoice amount of $'.$invoice->amount.'.',
            ]);
        }
    }

    /**
     * A unique receipt number from the receipt numbering configuration
     * (prefix + random suffix) that never collides with an existing receipt.
     */
    private function nextReceiptNo(): string
    {
        $prefix = (string) $this->config->get('numbering.receipt.prefix', 'RCT');
        $length = (int) $this->config->get('numbering.receipt.length', 8);

        do {
            $receiptNo = $prefix.'-'.Str::upper(Str::random($length));
        } while (Payment::where('receipt_no', $receiptNo)->exists());

        return $receiptNo;
    }

    private function popRequired(): bool
    {
        return (bool) $this->config->get('payments.pop.approval_required', true);
    }

    private function threshold(): string
    {
        return (string) $this->config->get('payments.approval.threshold', '500.00');
    }

    private function normalise(string $value): string
    {
        return sprintf('%01.2f', (float) $value);
    }

    private function cents($value): int
    {
        return (int) round(((float) $value) * 100);
    }
}