import { Head } from '@inertiajs/react';
import { Receipt, Wallet, CalendarRange, AlertTriangle, CheckCircle2, FileText } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

export default function OwnerRentIndex({ schedules = [], summary = {}, statement = {} }) {
    const rows = statement.rows || [];

    return (
        <MainLayout title="Rent & Income">
            <Head title="Rent & Income" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Rent & Income</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Each active lease is billed automatically against a rent schedule — one invoice per calendar month. The lifecycle (draft → due → overdue), reminder timing and late fees are driven by the Configuration Centre.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Wallet} label="Invoiced to date" value={money(summary.invoiced)} hint="Total across all schedules" tone="slate" />
                <StatCard icon={CalendarRange} label="Due now" value={money(summary.due)} hint="Current-cycle invoices" tone="amber" />
                <StatCard icon={AlertTriangle} label="Overdue" value={money(summary.overdue)} hint="Past the billing date" tone="rose" />
                <StatCard icon={CheckCircle2} label="Paid" value={money(summary.paid)} hint="Settled invoices" tone="emerald" />
            </div>

            <section className="surface mb-8 overflow-hidden">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                    <div className="flex items-center gap-2">
                        <FileText className="h-4 w-4 text-primary" />
                        <h3 className="text-sm font-extrabold tracking-tight text-foreground">Outstanding rent & arrears</h3>
                    </div>
                    {Number(summary.outstanding || 0) > 0 && (
                        <span className="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-extrabold text-rose-700">
                            {money(summary.outstanding)} outstanding
                        </span>
                    )}
                </header>

                {rows.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                    <th className="py-2.5 pl-5 pr-4 font-bold">Property</th>
                                    <th className="py-2.5 pr-4 font-bold">Invoice</th>
                                    <th className="py-2.5 pr-4 font-bold">Period</th>
                                    <th className="py-2.5 pr-4 font-bold">Rent</th>
                                    <th className="py-2.5 pr-4 font-bold">Late fee</th>
                                    <th className="py-2.5 pr-5 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => {
                                    const late = Number(row.late_fee || 0);
                                    return (
                                        <tr key={row.id} className="border-b border-border/60 last:border-0">
                                            <td className="py-2.5 pl-5 pr-4">
                                                <p className="font-semibold text-foreground">{row.property?.title}</p>
                                                <p className="text-xs text-muted-foreground">{[row.property?.suburb, row.property?.city].filter(Boolean).join(', ')}</p>
                                            </td>
                                            <td className="py-2.5 pr-4">
                                                <p className="font-mono text-xs font-bold text-foreground">{row.invoice_no}</p>
                                                <p className="text-xs text-muted-foreground">{row.lease_no}</p>
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
                        No outstanding invoices — all rent is settled.
                    </div>
                )}
            </section>

            {schedules.length === 0 ? (
                <EmptyState
                    icon={Receipt}
                    title="No rent schedules yet"
                    description="When an active lease is signed, its rent schedule and monthly invoices appear here automatically."
                />
            ) : (
                <div className="space-y-6">
                    {schedules.map((schedule) => (
                        <section key={schedule.id} className="surface overflow-hidden">
                            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold text-foreground">{schedule.lease?.property?.title || 'Property'}</p>
                                    <p className="mt-0.5 font-mono text-xs font-bold text-muted-foreground">{schedule.lease?.lease_no}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-semibold text-muted-foreground">{money(schedule.rent_amount)} / month</span>
                                    <StatusBadge status={schedule.lease?.status} />
                                </div>
                            </header>

                            <div className="flex flex-wrap items-center gap-2.5 border-b border-border/60 px-5 py-2.5 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1 font-semibold text-foreground">
                                    <CalendarRange className="h-3.5 w-3.5" />
                                    {fmt(schedule.start_date)} → {fmt(schedule.end_date)}
                                </span>
                                <span aria-hidden className="text-border">·</span>
                                <span>{schedule.invoices?.length || 0} monthly invoice{(schedule.invoices?.length || 0) === 1 ? '' : 's'}</span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[560px] text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                            <th className="py-2.5 pl-5 pr-4 font-bold">Invoice</th>
                                            <th className="py-2.5 pr-4 font-bold">Period</th>
                                            <th className="py-2.5 pr-4 font-bold">Amount</th>
                                            <th className="py-2.5 pr-4 font-bold">Late fee</th>
                                            <th className="py-2.5 pr-5 font-bold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(schedule.invoices || []).map((invoice) => {
                                            const late = Number(invoice.late_fee || 0);
                                            return (
                                                <tr key={invoice.id} className="border-b border-border/60 last:border-0">
                                                    <td className="py-2.5 pl-5 pr-4 font-mono text-xs font-bold text-foreground">{invoice.invoice_no}</td>
                                                    <td className="py-2.5 pr-4 text-muted-foreground">
                                                        {fmt(invoice.period_start)} → {fmt(invoice.period_end)}
                                                    </td>
                                                    <td className="py-2.5 pr-4 font-semibold text-foreground">{money(invoice.amount)}</td>
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
                                                        <StatusBadge status={invoice.status} />
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