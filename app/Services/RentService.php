<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\RentInvoice;
use App\Models\RentSchedule;
use App\Models\User;
use App\Notifications\RentInvoiceDueNotification;
use App\Notifications\RentInvoiceOverdueNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rent invoicing for active leases (Module 09, Wave 4 slice 3).
 *
 * Slice 3 covers FR-01 (schedule + invoice generation from an active lease,
 * invoice timing + reminders from configuration) and the draft → due → overdue
 * lifecycle of FR-03. Payment settlement (paid + receipt no), deposits and
 * arrears/late fees land in the following slices of Wave 4.
 *
 * Every timing/numbering rule reads `ConfigurationService` (Module 24) — never
 * a code constant: `invoices.timing`, `invoices.reminder_lead_days`,
 * `invoices.overdue_days` and `numbering.rent_invoice.prefix` (+ the shared
 * padding/start/year-reset keys).
 */
final class RentService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Generate the rent schedule + one invoice per calendar month for an
     * active lease (FR-01). Idempotent: a lease already on a schedule is
     * never double-billed, and non-active leases generate nothing.
     */
    public function generateFor(Lease $lease): void
    {
        if ($lease->status !== 'active') {
            return;
        }

        if ($lease->rentSchedule()->exists()) {
            return;
        }

        DB::transaction(function () use ($lease) {
            $schedule = RentSchedule::create([
                'lease_id' => $lease->id,
                'start_date' => $lease->start_date,
                'end_date' => $lease->end_date,
                'rent_amount' => $lease->rent_amount,
                'payment_terms' => $lease->payment_terms,
            ]);

            foreach ($this->buildPeriods($lease->start_date, $lease->end_date) as $period) {
                RentInvoice::create([
                    'property_id' => $lease->property_id,
                    'tenant_id' => $lease->tenant_id,
                    'lease_id' => $lease->id,
                    'schedule_id' => $schedule->id,
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'amount' => $lease->rent_amount,
                    'status' => 'draft',
                    'invoice_no' => $this->nextInvoiceNo(),
                ]);
            }
        });
    }

    /**
     * Split the lease term into contiguous calendar months. Each period runs
     * from the previous month's end (or the term start) to the end of its own
     * calendar month; the first and last periods clamp to the term boundaries,
     * so the whole term is covered exactly once and periods never overlap.
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    private function buildPeriods(Carbon $start, Carbon $end): array
    {
        $periods = [];
        $cursor = $start->copy()->startOfDay();
        $limit = $end->copy()->startOfDay();

        while (true) {
            $periodEnd = $cursor->copy()->endOfMonth();
            if ($periodEnd->greaterThan($limit)) {
                $periodEnd = $limit->copy();
            }

            $periods[] = ['start' => $cursor->copy(), 'end' => $periodEnd->copy()];

            if ($periodEnd->greaterThanOrEqualTo($limit)) {
                break;
            }

            $cursor = $periodEnd->copy()->addDay()->startOfMonth();
        }

        return $periods;
    }

    /**
     * Advance every unsettled invoice through the lifecycle in one sweep
     * (scheduled via `rent:process`):
     *  - draft → due once the configured due date arrives (`invoices.timing`);
     *  - due → overdue once the cycle plus `invoices.overdue_days` has passed;
     *  - a one-time due reminder to the tenant within `invoices.reminder_lead_days`
     *    of the due date (recorded in `reminded_at`, never repeated).
     */
    public function runInvoiceLifecycle(bool $notify = true): void
    {
        RentInvoice::whereIn('status', ['draft', 'due'])->get()
            ->each(function (RentInvoice $invoice) use ($notify) {
                $this->advance($invoice, $notify);
            });

        $this->accrueLateFees();
    }

    private function advance(RentInvoice $invoice, bool $notify = true): void
    {
        $dueDate = $this->dueDate($invoice);

        if ($invoice->status === 'draft' && $dueDate->lte(Carbon::today())) {
            $invoice->update(['status' => 'due']);
        }

        if ($invoice->status === 'due' && $this->overdueStart($invoice)->lt(Carbon::today())) {
            $invoice->update(['status' => 'overdue']);
            if ($notify) {
                $this->notifyOverdue($invoice);
            }
        }

        $this->sendReminderIfDue($invoice, $dueDate);
    }

    /**
     * Notify both parties the first time an invoice falls overdue (FR-06
     * "overdue flags/notices"). The transition fires exactly once, so the
     * notice is never repeated for the same invoice.
     */
    private function notifyOverdue(RentInvoice $invoice): void
    {
        $invoice->tenant->notify(new RentInvoiceOverdueNotification($invoice->fresh(['property.owner'])));

        if ($owner = $invoice->property->owner) {
            $owner->notify(new RentInvoiceOverdueNotification($invoice->fresh(['property'])));
        }
    }

    // ---------- late fees (FR-06, config-driven) ----------

    /**
     * Recompute the accrued late fee on every overdue invoice from the
     * `late_fees.*` configuration. The charge is deterministic — derived
     * purely from the invoice, the configuration and the clock — so running
     * this method any number of times never changes the stored figure (no
     * drift, no double accrual). A documented `late_fee` is written back to
     * the invoice so the ledger always carries the exact amount a tenant owes.
     */
    public function accrueLateFees(?Carbon $asOf = null): void
    {
        if (! $this->lateFeesEnabled()) {
            return;
        }

        $asOf = $asOf?->copy() ?? Carbon::today();

        RentInvoice::where('status', 'overdue')->get()
            ->each(function (RentInvoice $invoice) use ($asOf) {
                $fee = $this->lateFeeFor($invoice, $asOf);
                if ($this->cents($fee) !== $this->cents($invoice->late_fee)) {
                    $invoice->update(['late_fee' => $fee]);
                }
            });
    }

    /**
     * The exact late fee an invoice has accrued up to the given date, as a
     * two-decimal money string. One charge is applied per configured period
     * since the invoice became overdue, capped at `late_fees.cap`.
     */
    private function lateFeeFor(RentInvoice $invoice, Carbon $asOf): string
    {
        $daysOverdue = $this->overdueStart($invoice)->diffInDays($asOf);
        if ($daysOverdue < 0) {
            return '0.00';
        }

        $periodDays = max(1, (int) $this->config->get('late_fees.period_days', 30));
        $periods = intdiv($daysOverdue, $periodDays) + 1;

        $type = (string) $this->config->get('late_fees.type', 'percent');
        $value = (string) $this->config->get('late_fees.value', '0.00');

        $perPeriod = $type === 'fixed'
            ? $this->money($value)
            : $this->money(((float) $invoice->amount) * ((float) $value) / 100);

        $totalCents = $periods * $this->cents($perPeriod);

        $cap = (string) $this->config->get('late_fees.cap', '0.00');
        $capCents = $this->cents($cap);
        if ($capCents > 0 && $totalCents > $capCents) {
            $totalCents = $capCents;
        }

        return $this->moneyFromCents($totalCents);
    }

    private function lateFeesEnabled(): bool
    {
        return (bool) $this->config->get('late_fees.enabled', false);
    }

    /**
     * The day from which an invoice is considered overdue: the end of its
     * billing period plus the configured `invoices.overdue_days` grace.
     */
    private function overdueStart(RentInvoice $invoice): Carbon
    {
        return $invoice->period_end->copy()->startOfDay()->addDays($this->overdueDays());
    }

    /**
     * Remind the tenant once per invoice within the configured lead window
     * (due date minus `invoices.reminder_lead_days` through the due date).
     * Invoices already past their due date are not reminded again.
     */
    private function sendReminderIfDue(RentInvoice $invoice, Carbon $dueDate): void
    {
        if ($invoice->reminded_at !== null || $invoice->status === 'overdue') {
            return;
        }

        $windowStart = $dueDate->copy()->subDays($this->reminderLeadDays());
        if (! Carbon::today()->between($windowStart, $dueDate)) {
            return;
        }

        $invoice->tenant->notify(new RentInvoiceDueNotification($invoice->fresh(['property', 'lease'])));
        $invoice->update(['reminded_at' => now()]);
    }

    /**
     * The day an invoice becomes due, per `invoices.timing`:
     * `billing_date` (default) — due at the start of its billing period;
     * `immediate` — due as soon as it is generated.
     */
    private function dueDate(RentInvoice $invoice): Carbon
    {
        $timing = (string) $this->config->get('invoices.timing', 'billing_date');

        return $timing === 'immediate'
            ? $invoice->created_at->copy()->startOfDay()
            : $invoice->period_start->copy()->startOfDay();
    }

    private function reminderLeadDays(): int
    {
        return (int) $this->config->get('invoices.reminder_lead_days', 3);
    }

    private function overdueDays(): int
    {
        return (int) $this->config->get('invoices.overdue_days', 0);
    }

    /**
     * A unique rent invoice number from the numbering configuration
     * (`numbering.rent_invoice.prefix`, sharing padding/start/year-reset with
     * the invoice group) — old invoices are never renumbered.
     */
    private function nextInvoiceNo(): string
    {
        $prefix = (string) $this->config->get('numbering.rent_invoice.prefix', 'RNT');
        $padding = (int) $this->config->get('numbering.invoice.padding', 4);
        $start = (int) $this->config->get('numbering.invoice.start', 1);
        $yearReset = (bool) $this->config->get('numbering.invoice.year_reset', true);

        $yearPrefix = $yearReset ? $prefix.'-'.now()->year.'-' : $prefix.'-';
        $sequenceBase = RentInvoice::where('invoice_no', 'like', $yearPrefix.'%')->count() + $start;

        do {
            $sequence = Str::padLeft((string) $sequenceBase, $padding, '0');
            $invoiceNo = $yearPrefix.$sequence;
            $sequenceBase++;
        } while (RentInvoice::where('invoice_no', $invoiceNo)->exists());

        return $invoiceNo;
    }

    /**
     * Schedules (with their invoices) against an owner's properties.
     */
    public function schedulesForOwner(User $owner)
    {
        return RentSchedule::with([
            'lease:id,lease_no,start_date,end_date,status,rent_amount,property_id',
            'lease.property:id,title,cover_image,suburb,city,status,owner_id',
            'invoices:id,schedule_id,period_start,period_end,amount,late_fee,status,invoice_no,reminded_at',
        ])
            ->whereHas('lease.property', fn ($query) => $query->where('owner_id', $owner->id))
            ->latest('id')
            ->get();
    }

    /**
     * The owner's invoicing totals across every schedule (server-side, from
     * the ledger — never client arithmetic). `outstanding` is everything the
     * tenant still owes (due + overdue amounts + accrued late fees) and feeds
     * the landlord dashboard.
     */
    public function ownerSummary(User $owner): array
    {
        $invoices = RentInvoice::whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))->get();

        return [
            'invoiced' => $invoices->sum('amount'),
            'due' => $invoices->where('status', 'due')->sum('amount'),
            'overdue' => $invoices->where('status', 'overdue')->sum('amount'),
            'paid' => $invoices->whereIn('status', ['paid', 'refunded'])->sum('amount'),
            'late_fees' => $this->money($invoices->where('status', 'overdue')->sum('late_fee')),
            'outstanding' => $this->money(
                $invoices->whereIn('status', ['due', 'overdue'])->sum('amount')
                + $invoices->where('status', 'overdue')->sum('late_fee')
            ),
        ];
    }

    /**
     * Invoices owed by one tenant (any lease), newest period first.
     */
    public function invoicesForTenant(User $tenant)
    {
        return RentInvoice::with([
            'property:id,title,cover_image,suburb,city,status',
            'lease:id,lease_no,start_date,end_date,status',
            'payment:id,invoice_id,status,receipt_no,method,reference,paid_at',
        ])
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('period_start')
            ->get();
    }

    /**
     * The tenant's rent totals across every lease.
     */
    public function tenantSummary(User $tenant): array
    {
        $invoices = $this->invoicesForTenant($tenant);

        return [
            'due' => $invoices->where('status', 'due')->sum('amount'),
            'overdue' => $invoices->where('status', 'overdue')->sum('amount'),
            'paid' => $invoices->whereIn('status', ['paid', 'refunded'])->sum('amount'),
            'late_fees' => $this->money($invoices->where('status', 'overdue')->sum('late_fee')),
            'outstanding' => $this->money(
                $invoices->whereIn('status', ['due', 'overdue'])->sum('amount')
                + $invoices->where('status', 'overdue')->sum('late_fee')
            ),
        ];
    }

    // ---------- arrear statements (FR-06/FR-07) ----------

    /**
     * The tenant's statement of everything still owing, grouped by invoice
     * with the accrued late fee and days overdue. Totals are computed
     * server-side straight from the ledger (AC-04).
     */
    public function tenantStatement(User $tenant): array
    {
        $rows = $this->invoicesForTenant($tenant)
            ->whereIn('status', ['due', 'overdue'])
            ->values()
            ->map(fn (RentInvoice $invoice) => $this->statementRow($invoice))
            ->all();

        return $this->statementTotals($rows);
    }

    /**
     * The owner's statement of outstanding rent across their properties,
     * one row per unpaid invoice (dues + arrears with accumulated late fees).
     */
    public function ownerStatement(User $owner): array
    {
        $rows = RentInvoice::with([
            'property:id,title,cover_image,suburb,city,status',
            'lease:id,lease_no,start_date,end_date,status',
        ])
            ->whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))
            ->whereIn('status', ['due', 'overdue'])
            ->latest('period_start')
            ->get()
            ->map(fn (RentInvoice $invoice) => $this->statementRow($invoice))
            ->all();

        return $this->statementTotals($rows);
    }

    private function statementRow(RentInvoice $invoice): array
    {
        $isOverdue = $invoice->status === 'overdue';

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'status' => $invoice->status,
            'property' => [
                'id' => $invoice->property->id,
                'title' => $invoice->property->title,
                'suburb' => $invoice->property->suburb,
                'city' => $invoice->property->city,
            ],
            'lease_no' => $invoice->lease->lease_no,
            'period' => [
                'start' => $invoice->period_start->toDateString(),
                'end' => $invoice->period_end->toDateString(),
            ],
            'amount' => $invoice->amount,
            'late_fee' => $invoice->late_fee ?? '0.00',
            'days_overdue' => $isOverdue ? $this->overdueStart($invoice)->diffInDays(Carbon::today()) : 0,
        ];
    }

    private function statementTotals(array $rows): array
    {
        $due = 0.0;
        $overdue = 0.0;
        $lateFees = 0.0;
        $arrears = 0.0;

        foreach ($rows as $row) {
            if ($row['status'] === 'overdue') {
                $overdue += (float) $row['amount'];
                $arrears += (float) $row['amount'] + (float) $row['late_fee'];
            } else {
                $due += (float) $row['amount'];
            }
            $lateFees += (float) $row['late_fee'];
        }

        return [
            'rows' => $rows,
            'due_total' => $this->money($due),
            'overdue_total' => $this->money($overdue),
            'late_fees_total' => $this->money($lateFees),
            'arrears_total' => $this->money($arrears),
            'outstanding_total' => $this->money($due + $overdue + $lateFees),
        ];
    }

    private function money(float|string|int $value): string
    {
        return sprintf('%01.2f', (float) $value);
    }

    private function cents(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function moneyFromCents(int $cents): string
    {
        return sprintf('%01.2f', $cents / 100);
    }
}