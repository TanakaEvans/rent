import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { Receipt, CalendarRange, AlertTriangle, CheckCircle2, ArrowUpRight, Download, Landmark, RotateCcw, FileText } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

function PayForm({ invoice, methods, popRequired, onDone }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        amount: invoice.amount,
        method: methods[0] || 'bank',
        reference: '',
        pop: null,
    });

    const needsPop = popRequired && ['bank', 'mobile'].includes(form.data.method);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('tenant.rent.pay', { invoice: invoice.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                onDone?.();
            },
        });
    };

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
            >
                <Landmark className="h-3.5 w-3.5" />
                Pay {money(invoice.amount)}
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-2.5 rounded-xl border border-border bg-card p-3 shadow-sm">
            <div>
                <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Method</label>
                <select
                    value={form.data.method}
                    onChange={(e) => form.setData('method', e.target.value)}
                    className="w-full rounded-lg border border-input bg-background px-2.5 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {methods.map((m) => (
                        <option key={m} value={m}>
                            {m.charAt(0).toUpperCase() + m.slice(1)}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Reference</label>
                <input
                    type="text"
                    value={form.data.reference}
                    onChange={(e) => form.setData('reference', e.target.value)}
                    placeholder="Bank / mobile reference (optional)"
                    maxLength={255}
                    className="w-full rounded-lg border border-input bg-background px-2.5 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
            </div>

            {needsPop && (
                <div>
                    <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Proof of payment</label>
                    <input
                        type="file"
                        accept="image/*,application/pdf"
                        onChange={(e) => form.setData('pop', e.target.files[0] || null)}
                        className="w-full text-sm"
                    />
                </div>
            )}

            <div className="flex items-center justify-between gap-2 pt-1">
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted"
                >
                    <RotateCcw className="h-3.5 w-3.5" /> Cancel
                </button>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-60"
                >
                    {form.processing ? 'Recording…' : `Confirm ${money(invoice.amount)}`}
                </button>
            </div>

            {form.errors.amount && <p className="text-xs font-semibold text-rose-600">{form.errors.amount}</p>}
            {form.errors.method && <p className="text-xs font-semibold text-rose-600">{form.errors.method}</p>}
            {form.errors.pop && <p className="text-xs font-semibold text-rose-600">{form.errors.pop}</p>}
        </form>
    );
}

export default function TenantRent({ invoices = [], summary = {}, statement = {}, rules = {} }) {
    const methods = rules.methods?.length ? rules.methods : ['cash', 'bank', 'mobile', 'online'];
    const popRequired = Boolean(rules.pop_required);

    const groups = (invoices || []).reduce((acc, invoice) => {
        const key = invoice.lease_id;
        if (!acc[key]) {
            acc[key] = { lease_no: invoice.lease?.lease_no, property: invoice.property, items: [] };
        }
        acc[key].items.push(invoice);
        return acc;
    }, {});

    const settledCount = (invoices || []).filter((i) => i.status === 'paid').length;

    return (
        <MainLayout title="My Rent">
            <Head title="My Rent" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Rent</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Your rent is invoiced every month from your active lease. Pay a due or overdue invoice below — bank/mobile payments
                    {popRequired ? ' include a proof of payment' : ''}, and payments of {money(rules.approval_threshold)} or more are confirmed
                    by Dzimba staff before the invoice is settled.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-4">
                <StatCard icon={CalendarRange} label="Due now" value={money(summary.due)} hint="Current billing period" tone="amber" />
                <StatCard icon={AlertTriangle} label="Overdue" value={money(summary.overdue)} hint="Please settle outstanding invoices" tone="rose" />
                <StatCard icon={CheckCircle2} label="Paid" value={money(summary.paid)} hint={`${settledCount} settled invoice${settledCount === 1 ? '' : 's'}`} tone="emerald" />
                <StatCard icon={Receipt} label="Receipts" value={String(settledCount)} hint="Downloadable with unique numbers" tone="slate" />
            </div>

            <section className="surface mb-8 overflow-hidden">
                <header className="flex items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                    <div className="flex items-center gap-2">
                        <FileText className="h-4 w-4 text-primary" />
                        <h3 className="text-sm font-extrabold tracking-tight text-foreground">Statement of arrears</h3>
                    </div>
                    {Number(statement.outstanding_total || 0) > 0 && (
                        <span className="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-extrabold text-rose-700">
                            {money(statement.outstanding_total)} outstanding
                        </span>
                    )}
                </header>

                {statement.rows?.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                    <th className="py-2.5 pl-5 pr-4 font-bold">Invoice</th>
                                    <th className="py-2.5 pr-4 font-bold">Property</th>
                                    <th className="py-2.5 pr-4 font-bold">Period</th>
                                    <th className="py-2.5 pr-4 font-bold">Rent</th>
                                    <th className="py-2.5 pr-4 font-bold">Late fee</th>
                                    <th className="py-2.5 pr-5 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {statement.rows.map((row) => {
                                    const late = Number(row.late_fee || 0);
                                    return (
                                        <tr key={row.id} className="border-b border-border/60 last:border-0">
                                            <td className="py-2.5 pl-5 pr-4 font-mono text-xs font-bold text-foreground">{row.invoice_no}</td>
                                            <td className="py-2.5 pr-4">
                                                <p className="font-semibold text-foreground">{row.property?.title}</p>
                                                <p className="text-xs text-muted-foreground">{[row.property?.suburb, row.property?.city].filter(Boolean).join(', ')}</p>
                                            </td>
                                            <td className="py-2.5 pr-4 text-muted-foreground">
                                                {fmt(row.period?.start)} → {fmt(row.period?.end)}
                                            </td>
                                            <td className="py-2.5 pr-4 font-semibold text-foreground">{money(row.amount)}</td>
                                            <td className="py-2.5 pr-4">
                                                {late > 0 ? (
                                                    <span className="inline-flex items-center gap-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-xs font-bold text-rose-700">
                                                        <AlertTriangle className="h-3 w-3" /> {money(late)}
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="py-2.5 pr-5">
                                                <StatusBadge status={row.status} />
                                                {row.days_overdue > 0 && (
                                                    <span className="ml-1.5 text-xs font-semibold text-muted-foreground">{row.days_overdue}d overdue</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 border-t border-border bg-muted/20 px-5 py-3 text-xs font-bold">
                            <span className="text-muted-foreground">Late fees due: <span className="text-rose-700">{money(statement.late_fees_total)}</span></span>
                            <span className="text-muted-foreground">Arrears: <span className="text-rose-700">{money(statement.arrears_total)}</span></span>
                            <span className="text-foreground">Total outstanding: {money(statement.outstanding_total)}</span>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-2.5 px-5 py-4 text-sm text-muted-foreground">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />
                        You have no outstanding invoices. All rent is settled.
                    </div>
                )}
            </section>

            {invoices.length === 0 ? (
                <EmptyState
                    icon={Receipt}
                    title="No rent invoices yet"
                    description="Once your lease is active, your monthly rent invoices will appear here."
                />
            ) : (
                <div className="space-y-6">
                    {Object.values(groups).map((group) => (
                        <section key={group.lease_no} className="surface overflow-hidden">
                            <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                                <p className="font-mono text-sm font-extrabold tracking-tight text-foreground">{group.lease_no}</p>
                                {group.property && (
                                    <p className="mt-0.5 flex items-center gap-1 truncate text-sm font-bold text-foreground">
                                        {group.property.title}
                                        <ArrowUpRight className="h-3.5 w-3.5 text-muted-foreground" />
                                    </p>
                                )}
                            </header>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[680px] text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                            <th className="py-2.5 pl-5 pr-4 font-bold">Invoice</th>
                                            <th className="py-2.5 pr-4 font-bold">Period</th>
                                            <th className="py-2.5 pr-4 font-bold">Amount</th>
                                            <th className="py-2.5 pr-4 font-bold">Status</th>
                                            <th className="py-2.5 pr-5 font-bold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.items.map((invoice) => {
                                            const payment = invoice.payment;
                                            const awaiting = payment && payment.status === 'pending';
                                            const payable = ['due', 'overdue'].includes(invoice.status) && (!payment || payment.status === 'rejected');

                                            return (
                                                <tr key={invoice.id} className="border-b border-border/60 last:border-0">
                                                    <td className="py-2.5 pl-5 pr-4 font-mono text-xs font-bold text-foreground">{invoice.invoice_no}</td>
                                                    <td className="py-2.5 pr-4 text-muted-foreground">
                                                        {fmt(invoice.period_start)} → {fmt(invoice.period_end)}
                                                    </td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{money(invoice.amount)}</td>
                                                    <td className="py-2.5 pr-4">
                                                        <StatusBadge status={invoice.status} />
                                                    </td>
                                                    <td className="py-2.5 pr-5">
                                                        {awaiting ? (
                                                            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700">
                                                                Waiting for confirmation
                                                            </span>
                                                        ) : payment && payment.status === 'settled' ? (
                                                            <a
                                                                href={route('tenant.rent.receipt', { payment: payment.id })}
                                                                className="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary/80"
                                                            >
                                                                <Download className="h-3.5 w-3.5" />
                                                                {payment.receipt_no}
                                                            </a>
                                                        ) : payable ? (
                                                            <PayForm invoice={invoice} methods={methods} popRequired={popRequired} />
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">—</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}