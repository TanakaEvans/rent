import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Clock, Plus, Pencil, Trash2, MapPin, CalendarDays } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

const toLocalInput = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const formatNiceDate = (value) =>
    new Date(value).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

export default function OwnerViewingSlots({ property, slots = [] }) {
    const [editingId, setEditingId] = useState(null);
    const create = useForm({ starts_at: '', ends_at: '' });
    const edit = useForm({ starts_at: '', ends_at: '' });

    const submitCreate = (e) => {
        e.preventDefault();
        create.post(route('owner.viewing-slots.store', property.id), {
            preserveScroll: true,
            onSuccess: () => create.reset(),
        });
    };

    const startEdit = (slot) => {
        setEditingId(slot.id);
        edit.setData({ starts_at: toLocalInput(slot.starts_at), ends_at: toLocalInput(slot.ends_at) });
    };

    const submitEdit = (slotId) => {
        edit.put(route('owner.viewing-slots.update', [property.id, slotId]), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const remove = (slotId) => {
        router.delete(route('owner.viewing-slots.destroy', [property.id, slotId]), { preserveScroll: true });
    };

    return (
        <MainLayout title="Viewing Slots">
            <Head title="Viewing Slots" />

            <div className="mb-7 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link
                        href={route('owner.properties.show', property.id)}
                        className="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary hover:text-primary/80"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> {property.title}
                    </Link>
                    <h2 className="mt-1.5 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Viewing Slots</h2>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                        {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                    </p>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">
                    <CalendarDays className="h-3.5 w-3.5" /> {slots.length} upcoming
                </span>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1fr_1.5fr]">
                {/* Create slot */}
                <section className="surface h-fit p-5 sm:p-6">
                    <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">
                        <Plus className="h-4 w-4 text-emerald-500" /> Add a viewing slot
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">Tenants will pick one of these when requesting a viewing.</p>
                    <form onSubmit={submitCreate} className="mt-4 space-y-3">
                        <div>
                            <label className="field-label">Starts at</label>
                            <input
                                type="datetime-local"
                                value={create.data.starts_at}
                                onChange={(e) => create.setData('starts_at', e.target.value)}
                                className="field w-full"
                            />
                            {create.errors.starts_at && <p className="mt-1 text-xs font-semibold text-rose-600">{create.errors.starts_at}</p>}
                        </div>
                        <div>
                            <label className="field-label">Ends at</label>
                            <input
                                type="datetime-local"
                                value={create.data.ends_at}
                                onChange={(e) => create.setData('ends_at', e.target.value)}
                                className="field w-full"
                            />
                            {create.errors.ends_at && <p className="mt-1 text-xs font-semibold text-rose-600">{create.errors.ends_at}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={create.processing}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-500 disabled:opacity-50"
                        >
                            <CalendarClock className="h-4 w-4" /> {create.processing ? 'Adding…' : 'Add Slot'}
                        </button>
                    </form>
                </section>

                {/* Slot list */}
                <section>
                    {slots.length === 0 ? (
                        <EmptyState
                            icon={CalendarClock}
                            title="No viewing slots yet"
                            description="Add a few open slots and tenants will pick one to request a viewing."
                        />
                    ) : (
                        <ul className="space-y-3">
                            {slots.map((slot) => {
                                const editable = slot.status === 'available' && !slot.is_past;
                                return (
                                    <li key={slot.id} className="surface p-4 sm:p-5">
                                        {editingId === slot.id ? (
                                            <div className="flex flex-col gap-3">
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <input
                                                        type="datetime-local"
                                                        value={edit.data.starts_at}
                                                        onChange={(e) => edit.setData('starts_at', e.target.value)}
                                                        className="field w-full"
                                                    />
                                                    <input
                                                        type="datetime-local"
                                                        value={edit.data.ends_at}
                                                        onChange={(e) => edit.setData('ends_at', e.target.value)}
                                                        className="field w-full"
                                                    />
                                                </div>
                                                {edit.errors.starts_at && <p className="text-xs font-semibold text-rose-600">{edit.errors.starts_at}</p>}
                                                {edit.errors.ends_at && <p className="text-xs font-semibold text-rose-600">{edit.errors.ends_at}</p>}
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => submitEdit(slot.id)}
                                                        disabled={edit.processing}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-500"
                                                    >
                                                        Save
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditingId(null)}
                                                        className="rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground hover:bg-muted"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className={cn('min-w-0', slot.is_past && 'opacity-50')}>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h4 className="flex items-center gap-1.5 font-bold text-foreground">
                                                            <CalendarClock className="h-4 w-4 shrink-0 text-emerald-500" />
                                                            {formatNiceDate(slot.starts_at)}
                                                        </h4>
                                                        <StatusBadge status={slot.is_past ? 'expired' : slot.status} />
                                                    </div>
                                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <Clock className="h-3 w-3" />
                                                        until {formatNiceDate(slot.ends_at)}
                                                    </p>
                                                </div>
                                                {editable && (
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => startEdit(slot)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition-colors hover:border-primary/30 hover:text-primary"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" /> Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => remove(slot.id)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" /> Delete
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
        </MainLayout>
    );
}