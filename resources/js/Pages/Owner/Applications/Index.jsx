import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, MapPin, Clock3, CheckCircle2, XCircle, Star, UserRound, FileSignature } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import ActionErrors from '@/Components/Shared/ActionErrors';
import { cn } from '@/lib/utils';
import { formatPrice, priceSuffix, PAYMENT_TERM_LABELS } from '@/lib/listing';

const isoDay = (date) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const defaultLeaseDates = () => {
    const start = new Date();
    const end = new Date(start);
    end.setFullYear(end.getFullYear() + 1);
    return { start_date: isoDay(start), end_date: isoDay(end) };
};

const formatRelative = (value) => {
    if (!value) return '';
    const diff = Date.now() - new Date(value).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const reviewable = (status) => status === 'pending' || status === 'shortlisted';

export default function OwnerApplicationsIndex({ properties = [] }) {
    const [rejectFor, setRejectFor] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [leaseFor, setLeaseFor] = useState(null);
    const leaseForm = useForm(defaultLeaseDates());

    const total = properties.reduce((sum, property) => sum + property.applications.length, 0);

    const act = (name, id) => router.post(route(name, id), {}, { preserveScroll: true });
    const submitReject = (id) => {
        if (!rejectReason.trim()) return;
        router.post(route('owner.applications.reject', id), { reason: rejectReason }, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectFor(null);
                setRejectReason('');
            },
        });
    };

    const openLease = (id) => {
        leaseForm.setData(defaultLeaseDates());
        leaseForm.clearErrors();
        setLeaseFor(id);
    };

    const submitLease = (id) => {
        leaseForm.post(route('owner.applications.lease', id), { preserveScroll: true });
    };

    return (
        <MainLayout title="Applications">
            <Head title="Applications" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Rental Applications</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Applicants grouped by property — <span className="font-semibold text-foreground">{total}</span> total.
                    </p>
                </div>
            </div>

            <ActionErrors exclude={['start_date', 'end_date']} className="mb-5" />

            {properties.length === 0 ? (
                <EmptyState
                    icon={ClipboardCheck}
                    title="No applications yet"
                    description="When tenants apply to your listings, their applications will appear here for review."
                />
            ) : (
                <div className="space-y-5">
                    {properties.map((property) => (
                        <section key={property.id} className="surface overflow-hidden">
                            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                                <div className="flex items-center gap-3">
                                    <Link href={route('owner.properties.show', property.id)} className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                        <PropertyArt property={property} />
                                    </Link>
                                    <div className="min-w-0">
                                        <Link href={route('owner.properties.show', property.id)} className="truncate font-bold text-foreground hover:text-primary">
                                            {property.title}
                                        </Link>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                        </p>
                                    </div>
                                </div>
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                    <ClipboardCheck className="h-3.5 w-3.5" /> {property.applications.length} {property.applications.length === 1 ? 'application' : 'applications'}
                                </span>
                            </header>

                            <ul className="divide-y divide-border">
                                {property.applications.map((app) => (
                                    <li key={app.id} className="flex flex-col gap-3 px-5 py-4">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="flex items-center gap-2.5">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full brand-gradient text-xs font-extrabold text-white">
                                                    {(app.applicant?.name || '?').charAt(0).toUpperCase()}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-bold text-foreground">{app.applicant?.name || 'Tenant'}</p>
                                                    {app.applicant?.email && <p className="truncate text-xs text-muted-foreground">{app.applicant.email}</p>}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="hidden items-center gap-1 text-[11px] font-medium text-muted-foreground sm:inline-flex">
                                                    <Clock3 className="h-3 w-3" /> {formatRelative(app.created_at)}
                                                </span>
                                                <StatusBadge status={app.status} />
                                            </div>
                                        </div>

                                        {app.message && (
                                            <p className="rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm leading-relaxed text-foreground">“{app.message}”</p>
                                        )}

                                        {app.status === 'rejected' && app.reject_reason && (
                                            <p className="rounded-xl border-l-4 border-l-rose-400 bg-rose-50/60 px-3.5 py-2 text-xs font-medium text-rose-800">
                                                Rejected with note: {app.reject_reason}
                                            </p>
                                        )}

                                        {reviewable(app.status) && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => act('owner.applications.shortlist', app.id)}
                                                    className={cn(
                                                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors',
                                                        app.status === 'shortlisted'
                                                            ? 'border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100'
                                                            : 'border-border text-muted-foreground hover:bg-muted'
                                                    )}
                                                >
                                                    <Star className="h-3.5 w-3.5" /> {app.status === 'shortlisted' ? 'Shortlisted — undo' : 'Shortlist'}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => act('owner.applications.approve', app.id)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition-colors hover:bg-emerald-100"
                                                >
                                                    <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                                                </button>
                                                {rejectFor === app.id ? (
                                                    <span className="inline-flex flex-wrap items-center gap-2">
                                                        <input
                                                            autoFocus
                                                            value={rejectReason}
                                                            onChange={(e) => setRejectReason(e.target.value)}
                                                            placeholder="Reason (shown to the tenant)"
                                                            maxLength={1000}
                                                            className="field w-64"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => submitReject(app.id)}
                                                            disabled={!rejectReason.trim()}
                                                            className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-rose-700 disabled:opacity-40"
                                                        >
                                                            Confirm reject
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setRejectFor(null);
                                                                setRejectReason('');
                                                            }}
                                                            className="rounded-lg px-3 py-1.5 text-xs font-bold text-muted-foreground hover:bg-muted"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setRejectFor(app.id);
                                                            setRejectReason('');
                                                        }}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition-colors hover:bg-rose-100"
                                                    >
                                                        <XCircle className="h-3.5 w-3.5" /> Reject
                                                    </button>
                                                )}
                                            </div>
                                        )}

                                        {app.status === 'approved' && app.lease && (
                                            <p className="flex flex-wrap items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                                <FileSignature className="h-3.5 w-3.5" /> Lease {app.lease.lease_no} was generated from this application ({app.lease.status}) —
                                                <Link href={route('owner.leases.index')} className="underline hover:text-emerald-900">manage it under Leases</Link>.
                                            </p>
                                        )}
                                        {app.status === 'approved' && !app.lease && property.open_leases_count > 0 && (
                                            <p className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                                <FileSignature className="h-3.5 w-3.5" /> This property already has a lease in progress. End or complete it under Leases before creating another.
                                            </p>
                                        )}
                                        {app.status === 'approved' && !app.lease && property.open_leases_count === 0 && property.status !== 'available' && (
                                            <p className="flex flex-wrap items-center gap-1.5 text-xs font-semibold text-amber-700">
                                                <FileSignature className="h-3.5 w-3.5" /> The property is not Available. Set it to Available on the
                                                <Link href={route('owner.properties.show', property.id)} className="underline hover:text-amber-900">property page</Link>
                                                before creating a lease.
                                            </p>
                                        )}
                                        {app.status === 'approved' && !app.lease && property.open_leases_count === 0 && property.status === 'available' && (
                                            leaseFor === app.id ? (
                                                <form
                                                    onSubmit={(e) => {
                                                        e.preventDefault();
                                                        submitLease(app.id);
                                                    }}
                                                    className="grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 sm:grid-cols-[1fr_1fr_auto]"
                                                >
                                                    <p className="text-xs text-muted-foreground sm:col-span-3">
                                                        Rent {formatPrice(property.price, property.currency)}{priceSuffix(property.payment_terms)} · Deposit {formatPrice(property.deposit || 0, property.currency)} · Paid {(PAYMENT_TERM_LABELS[property.payment_terms] || 'Monthly').toLowerCase()} — taken from the listing.
                                                    </p>
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Lease starts</span>
                                                        <input
                                                            type="date"
                                                            required
                                                            value={leaseForm.data.start_date}
                                                            onChange={(e) => leaseForm.setData('start_date', e.target.value)}
                                                            className="field w-full"
                                                        />
                                                        {leaseForm.errors.start_date && <span className="mt-1 block text-xs font-semibold text-rose-600">{leaseForm.errors.start_date}</span>}
                                                    </label>
                                                    <label className="block">
                                                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Lease ends</span>
                                                        <input
                                                            type="date"
                                                            required
                                                            min={leaseForm.data.start_date}
                                                            value={leaseForm.data.end_date}
                                                            onChange={(e) => leaseForm.setData('end_date', e.target.value)}
                                                            className="field w-full"
                                                        />
                                                        {leaseForm.errors.end_date && <span className="mt-1 block text-xs font-semibold text-rose-600">{leaseForm.errors.end_date}</span>}
                                                    </label>
                                                    <div className="flex items-end gap-2">
                                                        <button
                                                            type="submit"
                                                            disabled={leaseForm.processing}
                                                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white transition-colors hover:bg-emerald-700 disabled:opacity-60"
                                                        >
                                                            <FileSignature className="h-4 w-4" /> {leaseForm.processing ? 'Creating…' : 'Create lease'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setLeaseFor(null)}
                                                            className="h-10 rounded-lg px-3 text-xs font-bold text-muted-foreground hover:bg-muted"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            ) : (
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openLease(app.id)}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-emerald-700"
                                                    >
                                                        <FileSignature className="h-3.5 w-3.5" /> Create lease
                                                    </button>
                                                    <span className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                                        <UserRound className="h-3.5 w-3.5" /> Approved applicant — choose the lease dates; rent and terms come from the listing.
                                                    </span>
                                                </div>
                                            )
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}