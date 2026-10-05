import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarClock, CalendarDays, MapPin, CheckCircle2, XCircle, UserRound, ArrowRightLeft, Ban, CalendarX2, Settings2 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

const formatSlot = (value) =>
    value ? new Date(value).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }) : 'Time to be confirmed';

const CONFIRM = {
    decline: 'Decline this viewing request? The tenant will be notified.',
    cancel: 'Cancel this viewing? The tenant will be notified and the time freed up.',
};

export default function OwnerViewings({ requests = [], properties = [] }) {
    const [reschedulingId, setReschedulingId] = useState(null);
    const reschedule = useForm({ slot_id: '' });

    const doAction = (action, id, payload = {}) => {
        if (CONFIRM[action] && !confirm(CONFIRM[action])) return;
        router.post(route(`owner.viewings.${action}`, id), payload, { preserveScroll: true });
    };

    const submitReschedule = (id) => {
        reschedule.post(route('owner.viewings.reschedule', id), {
            preserveScroll: true,
            onSuccess: () => setReschedulingId(null),
        });
    };

    const startReschedule = (booking) => {
        setReschedulingId(booking.id);
        reschedule.setData('slot_id', '');
    };

    return (
        <MainLayout title="Viewing Requests">
            <Head title="Viewing Requests" />

            <div className="mb-6">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Viewing Requests</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Accept a request to lock the slot, propose a different time, or decline. No-shows are recorded per tenant.
                </p>
            </div>

            {properties.length > 0 && (
                <section className="surface mb-6 p-5">
                    <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">
                        <Settings2 className="h-4 w-4 text-primary" /> Manage viewing times
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">Tenants can only request times you have opened. Pick a property to add, change or remove its viewing times.</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {properties.map((property) => (
                            <Link
                                key={property.id}
                                href={route('owner.viewing-slots.index', property.id)}
                                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/40 px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:border-primary/40 hover:text-primary"
                            >
                                <CalendarClock className="h-3.5 w-3.5" /> {property.title}
                                <span className="rounded-full bg-background px-1.5 text-[10px] font-extrabold text-muted-foreground">{property.open_slots_count} open</span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            {requests.length === 0 ? (
                <EmptyState
                    icon={CalendarClock}
                    title="No viewing requests"
                    description="Tenant requests will appear here once you open viewing times on your properties (see Manage viewing times above)."
                />
            ) : (
                <ul className="space-y-4">
                    {requests.map((booking) => {
                        const actor = {
                            requested: 'accept, decline or reschedule',
                            rescheduled: 'waiting for the tenant to confirm the new time',
                        }[booking.status] || '';
                        const candidateCount = booking.available_slots?.length ?? 0;
                        return (
                            <li key={booking.id} className="surface p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-extrabold text-foreground">{booking.property?.title}</h3>
                                            <StatusBadge status={booking.status} />
                                        </div>
                                        <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {[booking.property?.suburb, booking.property?.city].filter(Boolean).join(', ') || 'Harare'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm font-bold text-foreground">
                                        <UserRound className="h-4 w-4 text-primary" />
                                        {booking.tenant?.name}
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl bg-muted/60 p-3.5 text-sm">
                                    <span className="inline-flex items-center gap-1.5 font-bold text-foreground">
                                        <CalendarDays className="h-4 w-4 text-primary" /> {formatSlot(booking.start_at)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">until {formatSlot(booking.end_at)}</span>
                                    {booking.is_proposed && (
                                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Tenant suggested this time</span>
                                    )}
                                    {booking.outcome && (
                                        <span className={cn(
                                            'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                            booking.outcome === 'not_interested' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'
                                        )}>
                                            {booking.outcome === 'not_interested' ? 'Tenant passed after viewing' : booking.outcome}
                                        </span>
                                    )}
                                </div>

                                {booking.request_message && (
                                    <p className="mt-3 text-sm leading-relaxed text-muted-foreground">“{booking.request_message}”</p>
                                )}

                                {reschedulingId === booking.id ? (
                                    <div className="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-dashed border-border bg-muted/40 p-3.5">
                                        <div className="min-w-52 flex-1">
                                            <label className="field-label">Propose a different slot</label>
                                            <select
                                                value={reschedule.data.slot_id}
                                                onChange={(e) => reschedule.setData('slot_id', e.target.value)}
                                                className="field w-full"
                                            >
                                                <option value="">Choose an open slot…</option>
                                                {booking.available_slots?.map((slot) => (
                                                    <option key={slot.id} value={slot.id}>
                                                        {formatSlot(slot.starts_at)} – {formatSlot(slot.ends_at)}
                                                    </option>
                                                ))}
                                            </select>
                                            {reschedule.errors.slot_id && (
                                                <p className="mt-1 text-xs font-semibold text-rose-600">{reschedule.errors.slot_id}</p>
                                            )}
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => submitReschedule(booking.id)}
                                                disabled={!reschedule.data.slot_id || reschedule.processing}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-violet-500 disabled:opacity-50"
                                            >
                                                <ArrowRightLeft className="h-4 w-4" /> Propose
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setReschedulingId(null)}
                                                className="rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground hover:bg-muted"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : null}
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    {booking.status === 'requested' && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => doAction('accept', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-emerald-500"
                                            >
                                                <CheckCircle2 className="h-4 w-4" /> Accept & Lock Slot
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => doAction('decline', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <XCircle className="h-4 w-4" /> Decline
                                            </button>
                                            {candidateCount > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => startReschedule(booking)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-violet-300 hover:text-violet-700"
                                                >
                                                    <ArrowRightLeft className="h-4 w-4" /> Reschedule
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => doAction('cancel', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <Ban className="h-4 w-4" /> Cancel
                                            </button>
                                        </>
                                    )}
                                    {booking.status === 'rescheduled' && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => doAction('decline', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <XCircle className="h-4 w-4" /> Decline
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => doAction('cancel', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <Ban className="h-4 w-4" /> Cancel
                                            </button>
                                        </>
                                    )}
                                    {booking.status === 'accepted' && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => doAction('complete', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-sky-500"
                                            >
                                                <CheckCircle2 className="h-4 w-4" /> Mark Completed
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => doAction('no-show', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-orange-300 hover:text-orange-700"
                                            >
                                                <CalendarX2 className="h-4 w-4" /> No-show
                                            </button>
                                            {candidateCount > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => startReschedule(booking)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-violet-300 hover:text-violet-700"
                                                >
                                                    <ArrowRightLeft className="h-4 w-4" /> Reschedule
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => doAction('cancel', booking.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <Ban className="h-4 w-4" /> Cancel
                                            </button>
                                        </>
                                    )}
                                    {actor && <span className="text-xs text-muted-foreground">— {actor}</span>}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </MainLayout>
    );
}