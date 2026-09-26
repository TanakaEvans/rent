<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RentInvoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * Private disk holding proof-of-payment uploads (never publicly served).
     */
    private const POP_DISK = 'local';

    public function __construct(private readonly PaymentService $payments)
    {
    }

    /**
     * Tenant pays one of their due/overdue invoices (FR-04). The invoice is
     * scoped to the tenant (404 for anyone else); the service decides whether
     * it settles immediately or waits for staff approval. A proof of payment
     * must be a JPG/PNG image or a PDF (max 4 MB) and is stored privately.
     */
    public function tenantStore(Request $request, RentInvoice $invoice)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'pop' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $popPath = null;
        if ($request->hasFile('pop')) {
            $popPath = $request->file('pop')->store('rent-pops', self::POP_DISK);
        }

        $this->payments->recordPayment($request->user(), $invoice, [
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'pop_path' => $popPath,
        ]);

        return redirect()->route('tenant.rent.index')
            ->with('success', 'Payment recorded. Your invoice settles once confirmed.');
    }

    /**
     * Download the tenant's own receipt for a settled payment (AC-03).
     */
    public function tenantReceipt(Request $request, Payment $payment): StreamedResponse
    {
        if ($payment->paid_by !== $request->user()->id) {
            abort(404);
        }

        return $this->receiptDownload($payment);
    }

    /**
     * Staff download the receipt of any settled payment.
     */
    public function adminReceipt(Payment $payment): StreamedResponse
    {
        return $this->receiptDownload($payment);
    }

    /**
     * Staff open the proof of payment attached to a payment.
     */
    public function adminProof(Payment $payment)
    {
        $disk = Storage::disk(self::POP_DISK);

        if (! $payment->pop_path || ! $disk->exists($payment->pop_path)) {
            abort(404);
        }

        return $disk->download(
            $payment->pop_path,
            'proof-of-payment-'.$payment->id.'.'.pathinfo($payment->pop_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * The staff approval queue: pending payments to confirm, and the recent
     * settlement trail.
     */
    public function adminIndex()
    {
        return Inertia::render('Admin/Payments/Index', [
            'pending' => $this->payments->pendingForAdmin(),
            'recent' => $this->payments->recentForAdmin(),
            'rules' => $this->payments->paymentRules(),
        ]);
    }

    /**
     * Staff confirm a pending payment → invoice settled.
     */
    public function adminApprove(Request $request, Payment $payment)
    {
        $this->payments->approve($request->user(), $payment);

        return redirect()->route('admin.rent.payments.index')
            ->with('success', 'Payment settled and receipt issued.');
    }

    /**
     * Staff reject a pending payment → invoice stays owing; the tenant is
     * notified with the optional note.
     */
    public function adminReject(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->payments->reject($request->user(), $payment, $validated['note'] ?? null);

        return redirect()->route('admin.rent.payments.index')
            ->with('success', 'Payment rejected — the invoice remains owing and the tenant has been notified.');
    }

    /**
     * Stream a plain-text receipt; only settled (or later refunded) payments
     * carry one.
     */
    private function receiptDownload(Payment $payment): StreamedResponse
    {
        if (! in_array($payment->status, ['settled', 'refunded'], true) || ! $payment->receipt_no) {
            abort(404);
        }

        $payment->load(['invoice.property', 'paidBy']);

        return response()->streamDownload(
            function () use ($payment) {
                echo $this->payments->renderReceiptText($payment);
            },
            'receipt-'.$payment->receipt_no.'.txt',
            ['Content-Type' => 'text/plain']
        );
    }
}
