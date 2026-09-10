<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RentInvoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /**
     * Tenant pays one of their due/overdue invoices (FR-04). The invoice is
     * scoped to the tenant (404 for anyone else); the service decides whether
     * it settles immediately or waits for staff approval.
     */
    public function tenantStore(Request $request, RentInvoice $invoice)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'pop' => ['nullable', 'file', 'max:4096'],
        ]);

        $popPath = null;
        if ($request->hasFile('pop')) {
            $popPath = $request->file('pop')->store('rent-pops');
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
     * Download a plain-text receipt for a settled payment (AC-03).
     */
    public function tenantReceipt(Request $request, Payment $payment): StreamedResponse
    {
        if ($payment->paid_by !== $request->user()->id && ! $request->user()->hasAnyRole(['Admin', 'Superuser'])) {
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
     * Staff reject a pending payment → invoice stays owing.
     */
    public function adminReject(Request $request, Payment $payment)
    {
        $this->payments->reject($request->user(), $payment);

        return redirect()->route('admin.rent.payments.index')
            ->with('success', 'Payment rejected — the invoice remains owing.');
    }
}