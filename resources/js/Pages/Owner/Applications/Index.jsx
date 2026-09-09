import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, MapPin, Clock3, CheckCircle2, XCircle, Star, UserRound, FileSignature } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

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

    const total = properties.reduce((sum, property) => sum + property.applications.length, 0);

    const act = (name, id) => router.post(route(name, id), {}, { preserveScroll: true });
    const submitReject = (id) => {
        if (!rejectReason.trim()) return;
        router.post(route('owner.applications.reject', id), { reason: rejectReason }, { preserveScroll: true });
        setRejectFor(null);
        setRejectReason('');
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

                                        {app.status === 'approved' && (
                                            property.leases_count === 0 ? (
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => act('owner.applications.lease', app.id)}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-emerald-700"
                                                    >
                                                        <FileSignature className="h-3.5 w-3.5" /> Create lease
                                                    </button>
                                                    <span className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                                        <UserRound className="h-3.5 w-3.5" /> Sole approved applicant — generates the lease with prefilled terms.
                                                    </span>
                                                </div>
                                            ) : (
                                                <p className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                                    <FileSignature className="h-3.5 w-3.5" /> Lease generated from this application — manage it under Leases.
                                                </p>
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