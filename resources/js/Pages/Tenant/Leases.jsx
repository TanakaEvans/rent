import { Head, Link, usePage } from '@inertiajs/react';
import { FileSignature, MapPin, UserRound, CalendarDays, Coins, ArrowUpRight, CheckCircle2, PenLine, RefreshCw, FileText } from 'lucide-react';
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

export default function TenantLeases({ leases = [] }) {
    const { user } = usePage().props.auth;

    return (
        <MainLayout title="My Leases">
            <Head title="My Leases" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Leases</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Review the pre-filled terms from your approved application, then sign when the owner sends the agreement.
                </p>
            </div>

            {leases.length === 0 ? (
                <EmptyState
                    icon={FileSignature}
                    title="No leases yet"
                    description="Once an owner approves your application and generates a lease, your agreement will appear here."
                />
            ) : (
                <div className="space-y-5">
                    {leases.map((lease) => {
                        const tenantSigned = lease.signatures?.some((sig) => sig.user_id === user.id);
                        return (
                            <section key={lease.id} className="surface overflow-hidden">
                                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                                    <div className="flex items-center gap-3">
                                        <Link href={route('marketplace.show', lease.property?.id)} className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                            <PropertyArt property={lease.property} />
                                        </Link>
                                        <div className="min-w-0">
                                            <p className="font-mono text-sm font-extrabold tracking-tight text-foreground">{lease.lease_no}</p>
                                            <Link href={route('marketplace.show', lease.property?.id)} className="inline-flex items-center gap-1 truncate text-sm font-bold text-foreground hover:text-primary">
                                                {lease.property?.title} <ArrowUpRight className="h-3.5 w-3.5" />
                                            </Link>
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
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Property owner</p>
                                            <p className="truncate text-sm font-bold text-foreground">{lease.property?.owner?.name || '—'}</p>
                                            {lease.property?.owner?.email && <p className="truncate text-xs text-muted-foreground">{lease.property.owner.email}</p>}
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
                                            <MapPin className="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Location</p>
                                            <p className="truncate text-sm font-bold text-foreground">
                                                {[lease.property?.suburb, lease.property?.city].filter(Boolean).join(', ') || 'On request'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">Clause v{lease.clause_version}</p>
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
                                            {lease.status === 'sent' && (tenantSigned ? 'You signed — awaiting the owner' : 'Awaiting your signature')}
                                            {lease.status === 'active' && 'Both parties signed — this lease is active'}
                                        </span>
                                    </div>
                                )}

                                <div className="border-t border-border bg-muted/30 px-5 py-3.5">
                                    {lease.status === 'draft' && (
                                        <p className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                            <PenLine className="h-4 w-4" /> Your agreement is ready — the owner will send it for signing shortly.
                                        </p>
                                    )}
                                    {lease.status === 'sent' && !tenantSigned && (
                                        <div>
                                            <p className="mb-2 text-xs font-semibold text-muted-foreground">Sign as the tenant</p>
                                            <LeaseSignPad label="Sign lease" routeName="tenant.leases.sign" leaseId={lease.id} userName={user.name} />
                                        </div>
                                    )}
                                    {lease.status === 'active' && (
                                        <p className="flex items-center gap-1.5 text-sm font-medium text-emerald-700">
                                            <CheckCircle2 className="h-4 w-4" /> Lease active — rent schedules and payments arrive in a later phase.
                                        </p>
                                    )}
                                    {lease.status === 'renewed' && (
                                        <p className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                            <FileText className="h-4 w-4" /> This lease was renewed — see the newer agreement above.
                                        </p>
                                    )}
                                </div>
                            </section>
                        );
                    })}
                </div>
            )}
        </MainLayout>
    );
}