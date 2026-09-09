import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, MapPin, Clock3, ArrowUpRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

const prose = {
    pending: 'The owner is reviewing your application. You will be notified on the decision.',
    shortlisted: 'You made the shortlist — keep an eye on this listing.',
    approved: 'Approved! The owner will reach out to start the lease.',
    rejected: 'This one did not work out, but your other applications are still active.',
};

export default function TenantApplications({ applications = [] }) {
    return (
        <MainLayout title="My Applications">
            <Head title="My Applications" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Applications</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Track the {applications.length} {applications.length === 1 ? 'application' : 'applications'} you have submitted.
                </p>
            </div>

            {applications.length === 0 ? (
                <EmptyState
                    icon={ClipboardCheck}
                    title="No applications yet"
                    description="Found a place you love? Apply directly to the owner in minutes — no agent fees."
                />
            ) : (
                <div className="space-y-4">
                    {applications.map((app) => (
                        <article key={app.id} className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:p-5">
                            <div className="flex-1">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h3 className="font-bold leading-snug text-foreground">{app.property?.title}</h3>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {[app.property?.suburb, app.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                                            <span className="font-bold text-foreground">${Number(app.property?.price).toLocaleString()}/mo</span>
                                        </p>
                                    </div>
                                    <StatusBadge status={app.status} />
                                </div>

                                {app.status === 'rejected' && app.reject_reason && (
                                    <div className="mt-3 rounded-xl border-l-4 border-l-rose-400 bg-rose-50/60 px-3.5 py-2.5">
                                        <p className="text-[10px] font-extrabold uppercase tracking-wider text-rose-700">Owner noted</p>
                                        <p className="mt-0.5 text-sm leading-relaxed text-foreground">{app.reject_reason}</p>
                                    </div>
                                )}

                                <p className="mt-3 text-xs font-medium leading-relaxed text-muted-foreground">{prose[app.status]}</p>

                                <p className="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                    <Clock3 className="h-3 w-3" /> Applied {formatDate(app.created_at)}
                                </p>
                            </div>

                            {app.property?.status === 'available' && (
                                <Link
                                    href={route('property.show', app.property_id)}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-primary/30 px-3.5 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white"
                                >
                                    View listing <ArrowUpRight className="h-3.5 w-3.5" />
                                </Link>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}