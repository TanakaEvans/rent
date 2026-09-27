import { useMemo } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, CalendarDays, MessageSquareText, MapPin, XCircle, CheckCircle2, Navigation, ExternalLink, Lightbulb } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import Calendar from '@/Components/Shared/Calendar';

const timeOf = (value) => value ? new Date(value).toLocaleString(undefined, { hour: 'numeric', minute: '2-digit' }) : '';
const formatSlot = (value) =>
    value ? new Date(value).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }) : 'Time to be confirmed';

const canCancel = (status) => ['requested', 'accepted', 'rescheduled'].includes(status);

export default function TenantViewings({ requests = [] }) {
    const confirmForm = useForm({});

    const events = useMemo(() => requests
        .filter((r) => r.start_at && !['declined', 'cancelled', 'no-show'].includes(r.status))
        .map((r) => ({ id: r.id, start: r.start_at, tone: r.status === 'accepted' ? 'green' : r.status === 'completed' ? 'slate' : 'amber' })), [requests]);

    const doConfirm = (id) => {
        confirmForm.post(route('tenant.viewings.confirm', id), { preserveScroll: true });
    };

    const doCancel = (id) => {
        if (!confirm('Cancel this viewing? The owner will be notified.')) return;
        router.post(route('tenant.viewings.cancel', id), {}, { preserveScroll: true });
    };

    return (
        <MainLayout title="My Viewings">
            <Head title="My Viewings" />

            <div className="mb-6">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Viewings</h2>
                <p className="mt-1 text-sm text-muted-foreground">Track your viewing requests across the marketplace.</p>
            </div>

            {requests.length === 0 ? (
                <EmptyState
                    icon={CalendarClock}
                    title="No viewings yet"
                    description="Open any available property and book a viewing time — your requests will appear here."
                />
            ) : (
                <div className="grid gap-5 lg:grid-cols-[minmax(0,320px)_1fr]">
                    <div className="surface h-fit p-5 lg:sticky lg:top-24">
                        <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Your schedule</h3>
                        <Calendar events={events} />
                        <div className="mt-4 flex flex-wrap gap-3 border-t border-border pt-3 text-[11px] font-semibold text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-green-500" /> Confirmed</span>
                            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-amber-500" /> Awaiting owner</span>
                            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-slate-400" /> Done</span>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    {requests.map((booking) => (
                        <div key={booking.id} className="surface p-5 transition-shadow hover:shadow-md">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 className="font-extrabold text-foreground">{booking.property?.title}</h3>
                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3 text-emerald-500" />
                                        {[booking.property?.suburb, booking.property?.city].filter(Boolean).join(', ') || 'Harare'}
                                    </p>
                                </div>
                                <StatusBadge status={booking.status} />
                            </div>

                            <div className="mt-4 rounded-xl bg-muted/60 p-3.5">
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                    <span className="inline-flex items-center gap-1.5 font-bold text-foreground">
                                        <CalendarDays className="h-4 w-4 text-primary" /> {formatSlot(booking.start_at)}
                                        {booking.end_at && <span className="font-medium text-muted-foreground"> – {timeOf(booking.end_at)}</span>}
                                    </span>
                                    {booking.is_proposed && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                            <Lightbulb className="h-3 w-3" /> Your suggested time
                                        </span>
                                    )}
                                </div>
                            </div>

                            {booking.request_message && (
                                <p className="mt-3 flex gap-2 text-sm leading-relaxed text-muted-foreground">
                                    <MessageSquareText className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" /> {booking.request_message}
                                </p>
                            )}

                            {booking.property?.locationExact && booking.property?.latitude && (
                                <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
                                    <p className="text-xs font-bold text-emerald-800">Viewing confirmed — here's the exact location:</p>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        <a
                                            href={`https://www.google.com/maps/dir/?api=1&destination=${booking.property.latitude},${booking.property.longitude}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500"
                                        >
                                            <Navigation className="h-3.5 w-3.5" /> Get directions
                                        </a>
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${booking.property.latitude},${booking.property.longitude}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5" /> Open in Google Maps
                                        </a>
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {booking.status === 'rescheduled' && (
                                    <button
                                        type="button"
                                        onClick={() => doConfirm(booking.id)}
                                        disabled={confirmForm.processing}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-emerald-500 disabled:opacity-50"
                                    >
                                        <CheckCircle2 className="h-4 w-4" /> Confirm New Time
                                    </button>
                                )}
                                {canCancel(booking.status) && (
                                    <button
                                        type="button"
                                        onClick={() => doCancel(booking.id)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <XCircle className="h-4 w-4" /> Cancel Viewing
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                    </div>
                </div>
            )}
        </MainLayout>
    );
}