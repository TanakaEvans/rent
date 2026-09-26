import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileSignature, MapPin, UserRound, CalendarDays, Coins, BadgeCheck, ArrowRight, CheckCircle2, Send, RefreshCw, CalendarPlus, FileText, CircleStop } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import LeaseSignPad from '@/Components/Shared/LeaseSignPad';
import ActionErrors from '@/Components/Shared/ActionErrors';
import { formatPrice, priceSuffix } from '@/lib/listing';

const leaseCurrency = (lease) => lease.currency || lease.property?.currency || 'USD';
const leaseTerm = (lease) => lease.payment_terms?.frequency || lease.property?.payment_terms || 'monthly';

const today = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

export default function OwnerLeasesIndex({ leases = [] }) {
    const { user } = usePage().props.auth;
    const [openRenewId, setOpenRenewId] = useState(null);
    const renewForm = useForm({ start_date: '', end_date: '' });
    const [openEndId, setOpenEndId] = useState(null);
    const endForm = useForm({ terminated_on: today(), reason: '' });

    const openEnd = (lease) => {
        if (openEndId === lease.id) {
            setOpenEndId(null);
            return;
        }
        endForm.setData({ terminated_on: today(), reason: '' });
        endForm.clearErrors();
        setOpenEndId(lease.id);
        setOpenRenewId(null);
    };

    const submitEnd = (lease) => {
        if (!confirm(`End lease ${lease.lease_no}? The tenant will be notified and the property freed up. This cannot be undone.`)) return;
        endForm.post(route('owner.leases.terminate', lease.id), {
            preserveScroll: true,
            onSuccess: () => setOpenEndId(null),
        });
    };

    const openRenew = (lease) => {
        if (openRenewId === lease.id) {
            setOpenRenewId(null);
            return;
        }
        renewForm.setData({
            start_date: lease.end_date ? String(lease.end_date).slice(0, 10) : '',
            end_date: '',
        });
        renewForm.clearErrors();
        setOpenRenewId(lease.id);
        setOpenEndId(null);
    };

    const submitRenew = (leaseId) => {
        renewForm.post(route('owner.leases.renew', leaseId), { preserveScroll: true });
    };

    return (
        <MainLayout title="Leases">
            <Head title="Leases" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Leases</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Draft agreements from approved applications — send them, wait for both signatures, and the property moves to occupied.
                </p>
            </div>

            <ActionErrors exclude={['start_date', 'end_date', 'terminated_on', 'reason', 'signature']} className="mb-5" />

            {leases.length === 0 ? (
                <EmptyState
                    icon={FileSignature}
                    title="No leases yet"
                    description="Approve an application in the Applications screen and generate a lease to draft an agreement."
                />
            ) : (
                <div className="space-y-5">
                    {leases.map((lease) => {
                        const ownerSigned = lease.signatures?.some((sig) => sig.user_id === user.id);
                        return (
                            <section key={lease.id} className="surface overflow-hidden">
                                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                                    <div className="flex items-center gap-3">
                                        <Link href={route('owner.properties.show', lease.property?.id)} className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                            <PropertyArt property={lease.property} />
                                        </Link>
                                        <div className="min-w-0">
                                            <p className="font-mono text-sm font-extrabold tracking-tight text-foreground">{lease.lease_no}</p>
                                            <Link href={route('owner.properties.show', lease.property?.id)} className="truncate text-sm font-bold text-foreground hover:text-primary">
                                                {lease.property?.title}
                                            </Link>
                                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                                {[lease.property?.suburb, lease.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={lease.status} />
                                </header>

                                {(lease.renewed_from || lease.renewals?.length > 0) && (
                                    <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-5 py-2.5">
                                        {lease.renewed_from && (
                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">
                                                <RefreshCw className="h-3.5 w-3.5" /> Renewal of {lease.renewed_from.lease_no}
                                            </span>
                                        )}
                                        {lease.renewals?.map((r) => (
                                            <span key={r.id} className="inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-700">
                                                <RefreshCw className="h-3.5 w-3.5" /> Renewed by {r.lease_no}
                                            </span>
                                        ))}
                                    </div>
                                )}

                                <div className="grid gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600">
                                            <UserRound className="h-4 w-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Tenant</p>
                                            <p className="truncate text-sm font-bold text-foreground">{lease.tenant?.name || '—'}</p>
                                            {lease.tenant?.email && <p className="truncate text-xs text-muted-foreground">{lease.tenant.email}</p>}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600">
                                            <CalendarDays className="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Term</p>
                                            <p className="text-sm font-bold text-foreground">
                                                {fmt(lease.start_date)} → {fmt(lease.end_date)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                                            <Coins className="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Terms</p>
                                            <p className="text-sm font-bold text-foreground">{formatPrice(lease.rent_amount, leaseCurrency(lease))}{priceSuffix(leaseTerm(lease))}</p>
                                            <p className="text-xs text-muted-foreground">Deposit {formatPrice(lease.deposit_amount || 0, leaseCurrency(lease))}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                            <BadgeCheck className="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Clause</p>
                                            <p className="text-sm font-bold text-foreground">v{lease.clause_version}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {lease.status === 'draft' && 'Ready to send'}
                                                {lease.status === 'sent' && 'Awaiting both signatures'}
                                                {lease.status === 'active' && 'Active — property occupied'}
                                                {lease.status === 'signed' && 'Signed by both parties'}
                                                {lease.status === 'renewed' && 'Superseded by its renewal'}
                                                {lease.status === 'terminated' && (lease.terminated_on ? `Ended ${fmt(lease.terminated_on)}` : 'Ended')}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {lease.status !== 'draft' && lease.signatures?.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-2 border-t border-border bg-muted/30 px-5 py-2.5">
                                        {lease.signatures.map((sig) => (
                                            <span key={sig.id} className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                                <CheckCircle2 className="h-3.5 w-3.5" />
                                                {sig.user?.name || 'Party'} · {fmt(sig.signed_at)}
                                            </span>
                                        ))}
                                        <span className="ml-auto text-xs font-medium text-muted-foreground">
                                            {lease.status === 'sent' && (ownerSigned ? 'You signed — awaiting the tenant' : 'Awaiting your signature')}
                                            {lease.status === 'active' && 'Both parties signed — tenant moved in to an occupied property'}
                                        </span>
                                    </div>
                                )}

                                {lease.status === 'terminated' && lease.termination_reason && (
                                    <p className="border-t border-border bg-rose-50/60 px-5 py-2.5 text-xs font-medium text-rose-800">
                                        Ended on {fmt(lease.terminated_on)} — {lease.termination_reason}
                                    </p>
                                )}

                                {lease.document && (
                                    <div className="flex flex-wrap items-center gap-2.5 border-t border-border bg-muted/30 px-5 py-2.5">
                                        <Link
                                            href={route('documents.show', lease.document.id)}
                                            className="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary/80"
                                        >
                                            <FileText className="h-3.5 w-3.5" /> View agreement
                                        </Link>
                                        <a
                                            href={route('documents.download', lease.document.id)}
                                            className="inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground hover:text-foreground"
                                        >
                                            Download
                                        </a>
                                        <span className="ml-auto text-xs font-medium text-muted-foreground">Agreement v{lease.document.version} stored</span>
                                    </div>
                                )}

                                <div className="border-t border-border px-5 py-3.5">
                                    {lease.status === 'draft' && (
                                        <button
                                            type="button"
                                            onClick={() => router.post(route('owner.leases.send', lease.id), {}, { preserveScroll: true })}
                                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                                        >
                                            <Send className="h-4 w-4" /> Send for signature
                                        </button>
                                    )}
                                    {lease.status === 'sent' && !ownerSigned && (
                                        <div>
                                            <p className="mb-2 text-xs font-semibold text-muted-foreground">Sign as the property owner</p>
                                            <LeaseSignPad label="Sign as owner" routeName="owner.leases.sign" leaseId={lease.id} userName={user.name} />
                                        </div>
                                    )}
                                    {lease.status === 'active' && (
                                        <div>
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-emerald-700">Lease active — this property is occupied and no longer listed.</p>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openRenew(lease)}
                                                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/[0.06] px-4 text-sm font-bold text-primary transition hover:bg-primary/15"
                                                    >
                                                        <RefreshCw className={openRenewId === lease.id ? 'h-4 w-4 rotate-180 transition' : 'h-4 w-4'} /> Renew lease
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openEnd(lease)}
                                                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-4 text-sm font-bold text-rose-700 transition hover:bg-rose-100"
                                                    >
                                                        <CircleStop className="h-4 w-4" /> End lease
                                                    </button>
                                                </div>
                                            </div>
                                            {openEndId === lease.id && (
                                                <form
                                                    onSubmit={(e) => {
                                                        e.preventDefault();
                                                        submitEnd(lease);
                                                    }}
                                                    className="mt-3 grid gap-3 rounded-xl border border-rose-200 bg-rose-50/50 p-4 sm:grid-cols-[1fr_2fr_auto]"
                                                >
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Lease ended on</span>
                                                        <input
                                                            type="date"
                                                            required
                                                            max={today()}
                                                            value={endForm.data.terminated_on}
                                                            onChange={(e) => endForm.setData('terminated_on', e.target.value)}
                                                            className="field w-full"
                                                        />
                                                        {endForm.errors.terminated_on && <span className="mt-1 block text-xs font-semibold text-rose-600">{endForm.errors.terminated_on}</span>}
                                                    </label>
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Reason (shared with the tenant)</span>
                                                        <input
                                                            type="text"
                                                            required
                                                            maxLength={1000}
                                                            value={endForm.data.reason}
                                                            onChange={(e) => endForm.setData('reason', e.target.value)}
                                                            placeholder="e.g. Tenant moved out by mutual agreement"
                                                            className="field w-full"
                                                        />
                                                        {endForm.errors.reason && <span className="mt-1 block text-xs font-semibold text-rose-600">{endForm.errors.reason}</span>}
                                                    </label>
                                                    <div className="flex items-end">
                                                        <button
                                                            type="submit"
                                                            disabled={endForm.processing || !endForm.data.reason.trim()}
                                                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-rose-600 px-4 text-sm font-bold text-white transition hover:bg-rose-700 disabled:opacity-60"
                                                        >
                                                            <CircleStop className="h-4 w-4" /> {endForm.processing ? 'Ending…' : 'End lease'}
                                                        </button>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground sm:col-span-3">
                                                        The property goes back to Available (or Unavailable if your plan has no free listing slot). Both of you are notified.
                                                    </p>
                                                </form>
                                            )}
                                            {openRenewId === lease.id && (
                                                <form
                                                    onSubmit={(e) => {
                                                        e.preventDefault();
                                                        submitRenew(lease.id);
                                                    }}
                                                    className="mt-3 grid gap-3 rounded-xl border border-primary/20 bg-primary/[0.04] p-4 sm:grid-cols-[1fr_1fr_auto]"
                                                >
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Renewal starts</span>
                                                        <input
                                                            type="date"
                                                            value={renewForm.data.start_date}
                                                            onChange={(e) => renewForm.setData('start_date', e.target.value)}
                                                            className="field w-full"
                                                        />
                                                        <span className="mt-1 block text-[11px] text-muted-foreground">Defaults to the current end date.</span>
                                                    </label>
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Renewal ends</span>
                                                        <input
                                                            type="date"
                                                            value={renewForm.data.end_date}
                                                            onChange={(e) => renewForm.setData('end_date', e.target.value)}
                                                            className="field w-full"
                                                        />
                                                        <span className="mt-1 block text-[11px] text-muted-foreground">Defaults to one year later.</span>
                                                    </label>
                                                    <div className="flex items-end gap-2">
                                                        <button
                                                            type="submit"
                                                            disabled={renewForm.processing}
                                                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
                                                        >
                                                            <CalendarPlus className="h-4 w-4" /> {renewForm.processing ? 'Drafting…' : 'Draft renewal'}
                                                        </button>
                                                    </div>
                                                    {(renewForm.errors.start_date || renewForm.errors.end_date) && (
                                                        <p className="sm:col-span-3 text-xs font-semibold text-rose-600">
                                                            {renewForm.errors.start_date || renewForm.errors.end_date}
                                                        </p>
                                                    )}
                                                </form>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </section>
                        );
                    })}
                </div>
            )}

            <div className="mt-7">
                <Link href={route('owner.applications.index')} className="inline-flex items-center gap-1.5 text-sm font-bold text-primary hover:text-primary/80">
                    Back to applications <ArrowRight className="h-4 w-4" />
                </Link>
            </div>
        </MainLayout>
    );
}