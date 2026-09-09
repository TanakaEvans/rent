import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileSignature, MapPin, UserRound, CalendarDays, Coins, BadgeCheck, ArrowRight, CheckCircle2, Send, RefreshCw, CalendarPlus } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import LeaseSignPad from '@/Components/Shared/LeaseSignPad';

const money = (value) => new Intl.NumberFormat('en-ZW', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

export default function OwnerLeasesIndex({ leases = [] }) {
    const { user } = usePage().props.auth;
    const [openRenewId, setOpenRenewId] = useState(null);
    const renewForm = useForm({ start_date: '', end_date: '' });

    const openRenew = (lease) => {
        if (openRenewId === lease.id) {
            setOpenRenewId(null);
            return;
        }
        renewForm.setData({
            start_date: lease.end_date ? String(lease.end_date).slice(0, 10) : '',
            end_date: '',
        });
        setOpenRenewId(lease.id);
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
                                            <p className="text-sm font-bold text-foreground">{money(lease.rent_amount)} / month</p>
                                            <p className="text-xs text-muted-foreground">Deposit {money(lease.deposit_amount)}</p>
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
                                                {['signed', 'renewed', 'terminated'].includes(lease.status) && 'See agreement status'}
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
                                                <button
                                                    type="button"
                                                    onClick={() => openRenew(lease)}
                                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/[0.06] px-4 text-sm font-bold text-primary transition hover:bg-primary/15"
                                                >
                                                    <RefreshCw className={openRenewId === lease.id ? 'h-4 w-4 rotate-180 transition' : 'h-4 w-4'} /> Renew lease
                                                </button>
                                            </div>
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