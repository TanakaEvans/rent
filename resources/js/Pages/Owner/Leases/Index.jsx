import { Head, Link } from '@inertiajs/react';
import { FileSignature, MapPin, UserRound, CalendarDays, Coins, BadgeCheck, ArrowRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';

const money = (value) => new Intl.NumberFormat('en-ZW', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
}).format(Number(value || 0));

export default function OwnerLeasesIndex({ leases = [] }) {
    return (
        <MainLayout title="Leases">
            <Head title="Leases" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Leases</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Draft agreements generated from approved applications — signatures are handled in a later step.
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
                    {leases.map((lease) => (
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
                                        <BadgeCheck className="h-4 w-4" />
                                    </span>
                                    <div>
                                        <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Clause</p>
                                        <p className="text-sm font-bold text-foreground">v{lease.clause_version}</p>
                                        <p className="text-xs text-muted-foreground">Awaiting tenant review</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    ))}
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