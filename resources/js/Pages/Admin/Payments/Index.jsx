import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ShieldCheck, Clock3, CheckCircle2, XCircle, Banknote, Download, Paperclip } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import { formatPrice } from '@/lib/listing';

const money = (value) => formatPrice(value || 0, 'USD');
const paymentMoney = (payment, value = payment.amount) => formatPrice(value || 0, payment.invoice?.property?.currency);
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

const post = (routeName, params, data = {}, success) =>
    router.post(route(routeName, params), data, { preserveScroll: true, onSuccess: () => success?.() });

function RejectControl({ payment }) {
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState('');

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition-colors hover:bg-rose-100"
            >
                <XCircle className="h-3.5 w-3.5" /> Reject
            </button>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <input
                type="text"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                maxLength={500}
                placeholder="Reason for the tenant (optional)"
                aria-label="Rejection reason"
                className="w-56 rounded-lg border border-input bg-background px-2.5 py-1.5 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <div className="flex items-center gap-1.5">
                <button
                    onClick={() => post('admin.rent.payments.reject', { payment: payment.id }, { note }, () => setOpen(false))}
                    className="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-rose-500"
                >
                    <XCircle className="h-3.5 w-3.5" /> Confirm reject
                </button>
                <button onClick={() => setOpen(false)} className="rounded-lg px-2 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted">
                    Cancel
                </button>
            </div>
        </div>
    );
}

export default function AdminPaymentsIndex({ pending = [], recent = [], rules = {} }) {
    const pendingTotal = pending.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const settled = recent.filter((p) => p.status === 'settled');
    const rejected = recent.filter((p) => p.status === 'rejected');

    return (
        <AdminLayout title="Payment Approvals">
            <Head title="Payment Approvals" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Payment Approvals</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Rent payments wait here when they need confirmation — {money(rules.approval_threshold)} or more, or bank/mobile
                    payments carrying a proof of payment. Approving settles the invoice and issues the tenant a receipt.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                <StatCard icon={Clock3} label="Awaiting confirmation" value={String(pending.length)} hint="Pending in the queue" tone="amber" />
                <StatCard icon={Banknote} label="Pending value" value={money(pendingTotal)} hint="Across all pending payments" tone="slate" />
                <StatCard icon={CheckCircle2} label="Approved" value={String(settled.length)} hint="From the last 10 decisions" tone="emerald" />
                <StatCard icon={XCircle} label="Rejected" value={String(rejected.length)} hint="From the last 10 decisions" tone="rose" />
            </div>

            <section className="surface mb-8 overflow-hidden">
                <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                    <h3 className="text-sm font-extrabold tracking-tight text-foreground">Pending payments</h3>
                </header>

                {pending.length === 0 ? (
                    <div className="p-6">
                        <EmptyState icon={ShieldCheck} title="Nothing waiting" description="All payments so far settled instantly or are below your approval threshold." />
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                    <th className="py-2.5 pl-5 pr-4 font-bold">Invoice</th>
                                    <th className="py-2.5 pr-4 font-bold">Property</th>
                                    <th className="py-2.5 pr-4 font-bold">Tenant</th>
                                    <th className="py-2.5 pr-4 font-bold">Amount</th>
                                    <th className="py-2.5 pr-4 font-bold">Method</th>
                                    <th className="py-2.5 pr-4 font-bold">Reference</th>
                                    <th className="py-2.5 pr-4 font-bold">Proof of payment</th>
                                    <th className="py-2.5 pr-5 font-bold">Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pending.map((p) => (
                                    <tr key={p.id} className="border-b border-border/60 last:border-0">
                                        <td className="py-2.5 pl-5 pr-4 font-mono text-xs font-bold text-foreground">{p.invoice?.invoice_no}</td>
                                        <td className="py-2.5 pr-4 font-semibold text-foreground">{p.invoice?.property?.title || '—'}</td>
                                        <td className="py-2.5 pr-4 text-muted-foreground">
                                            <div className="font-semibold text-foreground">{p.paid_by?.name}</div>
                                            <div className="text-xs">{p.paid_by?.email}</div>
                                        </td>
                                        <td className="py-2.5 pr-4 font-semibold text-foreground">
                                            {paymentMoney(p)}
                                            {Number(p.invoice?.late_fee || 0) > 0 && (
                                                <div className="text-xs font-semibold text-rose-700">incl. late fee {paymentMoney(p, p.invoice.late_fee)}</div>
                                            )}
                                        </td>
                                        <td className="py-2.5 pr-4 text-muted-foreground">{p.method?.charAt(0).toUpperCase() + p.method?.slice(1)}</td>
                                        <td className="py-2.5 pr-4 text-muted-foreground">{p.reference || '—'}</td>
                                        <td className="py-2.5 pr-4">
                                            {p.pop_path ? (
                                                <a
                                                    href={route('admin.rent.payments.pop', { payment: p.id })}
                                                    className="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary/80"
                                                >
                                                    <Paperclip className="h-3.5 w-3.5" /> View proof
                                                </a>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">None</span>
                                            )}
                                        </td>
                                        <td className="py-2.5 pr-5">
                                            <div className="flex items-start gap-2">
                                                <button
                                                    onClick={() => post('admin.rent.payments.approve', { payment: p.id })}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-emerald-500"
                                                >
                                                    <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                                                </button>
                                                <RejectControl payment={p} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="surface overflow-hidden">
                <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                    <h3 className="text-sm font-extrabold tracking-tight text-foreground">
                        Approved & rejected <span className="ml-1 font-semibold text-muted-foreground">(last {recent.length} decisions)</span>
                    </h3>
                </header>

                {recent.length === 0 ? (
                    <p className="px-5 py-6 text-sm text-muted-foreground">No approved or rejected payments yet. Approve a pending payment and it will be listed here.</p>
                ) : (
                    <>
                        {settled.length > 0 && (
                            <div className="border-b border-border/60">
                                <div className="flex items-center justify-between bg-emerald-50/60 px-5 py-2.5">
                                    <h4 className="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-emerald-700">
                                        <CheckCircle2 className="h-3.5 w-3.5" /> Approved ({settled.length})
                                    </h4>
                                    <span className="text-xs font-bold text-emerald-700">{money(settled.reduce((s, p) => s + Number(p.amount || 0), 0))}</span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[680px] text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                                <th className="py-2.5 pl-5 pr-4 font-bold">Receipt</th>
                                                <th className="py-2.5 pr-4 font-bold">Invoice</th>
                                                <th className="py-2.5 pr-4 font-bold">Tenant</th>
                                                <th className="py-2.5 pr-4 font-bold">Property</th>
                                                <th className="py-2.5 pr-4 font-bold">Amount</th>
                                                <th className="py-2.5 pr-5 font-bold">Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {settled.map((p) => (
                                                <tr key={p.id} className="border-b border-border/60 last:border-0">
                                                    <td className="py-2.5 pl-5 pr-4">
                                                        <a href={route('admin.rent.payments.receipt', { payment: p.id })} className="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-primary hover:text-primary/80">
                                                            <Download className="h-3.5 w-3.5" /> {p.receipt_no}
                                                        </a>
                                                    </td>
                                                    <td className="py-2.5 pr-4 font-mono text-xs font-bold text-foreground">{p.invoice?.invoice_no}</td>
                                                    <td className="py-2.5 pr-4 text-muted-foreground">{p.paid_by?.name}</td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{p.invoice?.property?.title || '—'}</td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{paymentMoney(p)}</td>
                                                    <td className="py-2.5 pr-5 text-muted-foreground">{p.paid_at && fmt(p.paid_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {rejected.length > 0 && (
                            <div>
                                <div className="flex items-center justify-between bg-rose-50/60 px-5 py-2.5">
                                    <h4 className="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-rose-700">
                                        <XCircle className="h-3.5 w-3.5" /> Rejected ({rejected.length})
                                    </h4>
                                    <span className="text-xs font-bold text-rose-700">{money(rejected.reduce((s, p) => s + Number(p.amount || 0), 0))}</span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[680px] text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                                <th className="py-2.5 pl-5 pr-4 font-bold">Invoice</th>
                                                <th className="py-2.5 pr-4 font-bold">Tenant</th>
                                                <th className="py-2.5 pr-4 font-bold">Property</th>
                                                <th className="py-2.5 pr-4 font-bold">Amount</th>
                                                <th className="py-2.5 pr-4 font-bold">Method</th>
                                                <th className="py-2.5 pr-5 font-bold">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rejected.map((p) => (
                                                <tr key={p.id} className="border-b border-border/60 last:border-0">
                                                    <td className="py-2.5 pl-5 pr-4 font-mono text-xs font-bold text-foreground">{p.invoice?.invoice_no}</td>
                                                    <td className="py-2.5 pr-4 text-muted-foreground">{p.paid_by?.name}</td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{p.invoice?.property?.title || '—'}</td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{paymentMoney(p)}</td>
                                                    <td className="py-2.5 pr-4 capitalize text-muted-foreground">{p.method}</td>
                                                    <td className="py-2.5 pr-5"><StatusBadge status={p.status} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </section>
        </AdminLayout>
    );
}