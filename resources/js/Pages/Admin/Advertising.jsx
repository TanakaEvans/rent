import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Megaphone, Clock3, Pause, Play, CheckCircle2, XCircle, BadgeDollarSign, Eye, ShieldCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

const post = (routeName, params, body = {}, success) =>
    router.post(route(routeName, params), body, { preserveScroll: true, onSuccess: () => success?.() });

function Actions({ placement }) {
    const [note, setNote] = useState('');
    const body = note ? { note } : {};

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                {placement.status === 'reserved' && (
                    <button
                        onClick={() => post('admin.advertising.approve', { placement: placement.id })}
                        className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-emerald-500"
                    >
                        <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                    </button>
                )}
                {placement.status === 'active' && (
                    <button
                        onClick={() => post('admin.advertising.pause', { placement: placement.id })}
                        className="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-100"
                    >
                        <Pause className="h-3.5 w-3.5" /> Pause
                    </button>
                )}
                {placement.status === 'paused' && (
                    <button
                        onClick={() => post('admin.advertising.resume', { placement: placement.id })}
                        className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-emerald-500"
                    >
                        <Play className="h-3.5 w-3.5" /> Resume
                    </button>
                )}
                {['reserved', 'active', 'paused'].includes(placement.status) && (
                    <button
                        onClick={() => post('admin.advertising.cancel', { placement: placement.id }, body)}
                        className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition-colors hover:bg-rose-100"
                    >
                        <XCircle className="h-3.5 w-3.5" /> Cancel
                    </button>
                )}
            </div>
            {['reserved', 'active', 'paused'].includes(placement.status) && (
                <input
                    className="field h-8 py-1 text-xs"
                    placeholder="Note (optional)…"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                />
            )}
        </div>
    );
}

export default function AdminAdvertising({ pending = [], live = [], history = [] }) {
    const pendingValue = pending.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    return (
        <AdminLayout title="Ad Placements">
            <Head title="Ad Placements" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Ad Placements</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Approve promotion orders, moderate live placements, and cancel with a prorated credit. Windows are
                    time-bounded and cleaned daily by the scheduler.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                <StatCard icon={Clock3} label="Awaiting approval" value={String(pending.length)} hint="Reserved orders" tone="amber" />
                <StatCard icon={BadgeDollarSign} label="Pending value" value={money(pendingValue)} hint="Across the queue" tone="slate" />
                <StatCard icon={Megaphone} label="Live placements" value={String(live.length)} hint="Active or paused" tone="emerald" />
            </div>

            <section className="surface mb-8 overflow-hidden">
                <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                    <h3 className="text-sm font-extrabold tracking-tight text-foreground">Approval queue</h3>
                </header>
                {pending.length === 0 ? (
                    <div className="p-6">
                        <EmptyState icon={ShieldCheck} title="Queue is clear" description="No promotion orders are waiting for approval." />
                    </div>
                ) : (
                    <div className="space-y-4 p-5">
                        {pending.map((p) => (
                            <article key={p.id} className="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-background/50 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h4 className="font-extrabold tracking-tight text-foreground">{p.property?.title || '—'}</h4>
                                        <StatusBadge status={p.status} />
                                    </div>
                                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {p.package?.name || '—'} · {money(p.amount)}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        Owner: <span className="font-semibold text-foreground">{p.owner?.name}</span> ({p.owner?.email})
                                    </div>
                                </div>
                                <Actions placement={p} />
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="surface mb-8 overflow-hidden">
                <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                    <h3 className="text-sm font-extrabold tracking-tight text-foreground">Live placements</h3>
                </header>
                {live.length === 0 ? (
                    <p className="px-5 py-6 text-sm text-muted-foreground">No promotions are running right now.</p>
                ) : (
                    <div className="space-y-4 p-5">
                        {live.map((p) => (
                            <article key={p.id} className="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-background/50 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h4 className="font-extrabold tracking-tight text-foreground">{p.property?.title || '—'}</h4>
                                        <StatusBadge status={p.status} />
                                    </div>
                                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {p.package?.name || '—'} · {money(p.amount)}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        Window: <span className="font-semibold text-foreground">{fmt(p.starts_at)} → {fmt(p.ends_at)}</span>
                                        {p.paused_at && <> · frozen since {fmt(p.paused_at)}</>}
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-2.5 py-1 text-[11px] font-bold text-muted-foreground">
                                            <Eye className="h-3.5 w-3.5 text-primary" /> {p.stats?.impressions || 0} impressions
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-2.5 py-1 text-[11px] font-bold text-muted-foreground">
                                            {p.stats?.clicks || 0} clicks · {p.stats?.enquiries || 0} enquiries · {p.stats?.applications || 0} applications
                                        </span>
                                    </div>
                                </div>
                                <Actions placement={p} />
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="surface overflow-hidden">
                <header className="border-b border-border bg-muted/30 px-5 py-3.5">
                    <h3 className="text-sm font-extrabold tracking-tight text-foreground">Recent history</h3>
                </header>
                {history.length === 0 ? (
                    <p className="px-5 py-6 text-sm text-muted-foreground">No expired or cancelled placements yet.</p>
                ) : (
                    <div className="space-y-4 p-5">
                        {history.map((p) => (
                            <article key={p.id} className="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-background/50 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h4 className="font-extrabold tracking-tight text-foreground">{p.property?.title || '—'}</h4>
                                        <StatusBadge status={p.status} />
                                    </div>
                                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {p.package?.name || '—'} · {money(p.amount)}
                                        {Number(p.credit_amount || 0) > 0 && (
                                            <span className="ml-2 font-bold text-emerald-600">credit {money(p.credit_amount)}</span>
                                        )}
                                    </div>
                                    {p.admin_note && <div className="mt-1 text-xs italic text-muted-foreground">{p.admin_note}</div>}
                                </div>
                                <span className="text-right text-xs text-muted-foreground">
                                    {p.ends_at ? `Window ended ${fmt(p.ends_at)}` : `Booked ${fmt(p.created_at)}`}
                                </span>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}