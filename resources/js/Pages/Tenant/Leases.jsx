import { Head, Link } from '@inertiajs/react';
import { FileSignature, MapPin, UserRound, CalendarDays, Coins, BadgeCheck, ArrowUpRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';

const money = (value) => new Intl.NumberFormat('en-ZW', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const statusProse = {
    draft: ['Your agreement is ready for review.', 'The owner will send it for signing shortly.'],
    sent: ['The agreement has been sent for signing.', 'Sign it in the Appointments & documents step.'],
    signed: ['Both parties have signed.', 'The lease becomes active at the start date.'],
    active: ['This lease is active.', 'Rent schedules and payments arrive in a later phase.'],
    renewed: ['This lease has been renewed.', 'See the renewed agreement for the new term.'],
    terminated: ['This lease has ended.', 'The property returns to the marketplace.'],
};

export default function TenantLeases({ leases = [] }) {
    return (
        <MainLayout title="My Leases">
            <Head title="My Leases" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Leases</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Your rental agreements — review the pre-filled terms from your approved application.
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
                    {leases.map((lease) => (
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
                                            {lease.start_date ? new Date(lease.start_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                                            {' → '}
                                            {lease.end_date ? new Date(lease.end_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
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

                            <div className="border-t border-border bg-muted/30 px-5 py-3">
                                <StatusBadge status={lease.status} className="mr-2" showDot={false} />
                                <span className="text-xs font-medium text-muted-foreground">
                                    {statusProse[lease.status]?.[0] || 'Status updated.'}
                                </span>
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}