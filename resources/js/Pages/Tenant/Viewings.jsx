import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, CalendarDays, MessageSquareText, MapPin, XCircle, CheckCircle2 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';

const formatSlot = (value) =>
    new Date(value).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

const canCancel = (status) => ['requested', 'accepted', 'rescheduled'].includes(status);

export default function TenantViewings({ requests = [] }) {
    const confirm = useForm({});

    const doConfirm = (id) => {
        confirm.post(route('tenant.viewings.confirm', id), { preserveScroll: true });
    };

    const doCancel = (id) => {
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
                    description="Open any available property and book a slot to see you started a viewing here."
                />
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
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
                                        <CalendarDays className="h-4 w-4 text-emerald-500" /> {formatSlot(booking.slot?.starts_at)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">until {formatSlot(booking.slot?.ends_at)}</span>
                                </div>
                            </div>

                            {booking.request_message && (
                                <p className="mt-3 flex gap-2 text-sm leading-relaxed text-muted-foreground">
                                    <MessageSquareText className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" /> {booking.request_message}
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {booking.status === 'rescheduled' && (
                                    <button
                                        type="button"
                                        onClick={() => doConfirm(booking.id)}
                                        disabled={confirm.processing}
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
            )}
        </MainLayout>
    );
}