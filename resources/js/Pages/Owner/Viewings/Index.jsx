import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, CalendarDays, MapPin, CheckCircle2, XCircle, UserRound, ArrowRightLeft, Ban, CalendarX2 } from 'lucide-react';
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

export default function OwnerViewings({ requests = [] }) {
    const [reschedulingId, setReschedulingId] = useState(null);
    const reschedule = useForm({ slot_id: '' });

    const doAction = (action, id, payload = {}) => {
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

            {requests.length === 0 ? (
                <EmptyState
                    icon={CalendarClock}
                    title="No viewing requests"
                    description="Tenant requests will appear here once you add slots to your properties."
                />
            ) : (
                <ul className="space-y-4">
                    {requests.map((booking) => {
                        const actor = booking.status === 'requested' ? 'accept, decline or reschedule' : '';
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
                                        <CalendarDays className="h-4 w-4 text-emerald-500" /> {formatSlot(booking.slot?.starts_at)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">until {formatSlot(booking.slot?.ends_at)}</span>
                                    {booking.outcome && (
                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                            {booking.outcome}
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