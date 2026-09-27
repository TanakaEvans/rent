import { useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, Plus, Pencil, Trash2, MapPin, CalendarDays, Users } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import EmptyState from '@/Components/Shared/EmptyState';
import Calendar from '@/Components/Shared/Calendar';
import { cn } from '@/lib/utils';

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const toLocalInput = (value) => {
    const d = new Date(value);
    return `${ymd(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const timeOf = (value) => new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
const longDay = (d) => d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

export default function OwnerViewingSlots({ property, slots = [], requests = [] }) {
    const [selectedDay, setSelectedDay] = useState(() => new Date());
    const [editingId, setEditingId] = useState(null);
    const create = useForm({ starts_at: '', ends_at: '' });
    const edit = useForm({ starts_at: '', ends_at: '' });

    const events = useMemo(() => [
        ...slots.map((s) => ({ id: `s${s.id}`, start: s.starts_at, tone: s.is_past ? 'slate' : s.status === 'taken' ? 'primary' : 'green' })),
        ...requests.filter((r) => r.starts_at).map((r) => ({ id: `r${r.id}`, start: r.starts_at, tone: 'amber' })),
    ], [slots, requests]);

    const selectedKey = ymd(selectedDay);
    const daySlots = slots.filter((s) => ymd(new Date(s.starts_at)) === selectedKey).sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at));
    const dayRequests = requests.filter((r) => r.starts_at && ymd(new Date(r.starts_at)) === selectedKey);
    const upcoming = slots.filter((s) => !s.is_past).length;

    const pickDay = (date) => {
        setSelectedDay(date);
        // Prefill the add-slot form to 10:00–10:30 on the chosen day.
        const start = new Date(date); start.setHours(10, 0, 0, 0);
        const end = new Date(date); end.setHours(10, 30, 0, 0);
        create.setData({ starts_at: toLocalInput(start), ends_at: toLocalInput(end) });
    };

    const submitCreate = (e) => {
        e.preventDefault();
        create.post(route('owner.viewing-slots.store', property.id), { preserveScroll: true, onSuccess: () => create.reset() });
    };
    const startEdit = (slot) => {
        setEditingId(slot.id);
        edit.setData({ starts_at: toLocalInput(slot.starts_at), ends_at: toLocalInput(slot.ends_at) });
    };
    const submitEdit = (slotId) => {
        edit.put(route('owner.viewing-slots.update', [property.id, slotId]), { preserveScroll: true, onSuccess: () => setEditingId(null) });
    };
    const remove = (slot) => {
        if (confirm(`Delete the viewing time at ${timeOf(slot.starts_at)}?`)) {
            router.delete(route('owner.viewing-slots.destroy', [property.id, slot.id]), { preserveScroll: true });
        }
    };

    return (
        <MainLayout title="Viewing Calendar">
            <Head title="Viewing Calendar" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href={route('owner.properties.show', property.id)} className="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary hover:text-primary/80">
                        <ArrowLeft className="h-3.5 w-3.5" /> {property.title}
                    </Link>
                    <h2 className="mt-1.5 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Viewing Calendar</h2>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin className="h-3.5 w-3.5 shrink-0 text-primary" />
                        {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                    </p>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-bold text-primary">
                    <CalendarDays className="h-3.5 w-3.5" /> {upcoming} upcoming slot{upcoming === 1 ? '' : 's'}
                </span>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1.1fr_1fr]">
                {/* Calendar */}
                <section className="surface p-5 sm:p-6">
                    <Calendar events={events} selected={selectedDay} onSelectDay={pickDay} minDate={null} />
                    <div className="mt-4 flex flex-wrap gap-3 border-t border-border pt-3 text-[11px] font-semibold text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-green-500" /> Open</span>
                        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-primary" /> Booked</span>
                        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-amber-500" /> Request</span>
                        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-slate-400" /> Past</span>
                    </div>
                </section>

                {/* Day panel */}
                <section className="space-y-4">
                    <div className="surface p-5 sm:p-6">
                        <h3 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">{longDay(selectedDay)}</h3>

                        {dayRequests.length > 0 && (
                            <div className="mt-3 space-y-2">
                                {dayRequests.map((r) => (
                                    <Link key={r.id} href={route('owner.viewings.index')} className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-xs transition hover:bg-amber-100">
                                        <Users className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
                                        <span>
                                            <span className="font-bold text-amber-900">{timeOf(r.starts_at)} · {r.tenant}</span>
                                            <span className="ml-1 text-amber-700">{r.is_proposed ? 'suggested this time' : `wants this slot (${r.status})`} — respond in Viewing Requests</span>
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}

                        <div className="mt-3 space-y-2">
                            {daySlots.length === 0 ? (
                                <p className="rounded-xl bg-muted/50 px-3.5 py-3 text-xs text-muted-foreground">No slots on this day yet. Add one below.</p>
                            ) : daySlots.map((slot) => (
                                <div key={slot.id} className={cn('rounded-xl border p-3', slot.is_past ? 'border-border opacity-60' : slot.status === 'taken' ? 'border-primary/30 bg-primary/5' : 'border-green-200 bg-green-50/60')}>
                                    {editingId === slot.id ? (
                                        <div className="space-y-2">
                                            <div className="grid grid-cols-2 gap-2">
                                                <input type="datetime-local" value={edit.data.starts_at} onChange={(e) => edit.setData('starts_at', e.target.value)} className="field w-full text-xs" />
                                                <input type="datetime-local" value={edit.data.ends_at} onChange={(e) => edit.setData('ends_at', e.target.value)} className="field w-full text-xs" />
                                            </div>
                                            {(edit.errors.starts_at || edit.errors.ends_at) && <p className="text-xs font-semibold text-rose-600">{edit.errors.starts_at || edit.errors.ends_at}</p>}
                                            <div className="flex gap-2">
                                                <button type="button" onClick={() => submitEdit(slot.id)} disabled={edit.processing} className="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground hover:bg-primary/90">Save</button>
                                                <button type="button" onClick={() => setEditingId(null)} className="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground hover:bg-muted">Cancel</button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="flex items-center gap-1.5 text-sm font-bold text-foreground">
                                                    <Clock className="h-3.5 w-3.5 text-primary" /> {timeOf(slot.starts_at)} – {timeOf(slot.ends_at)}
                                                </p>
                                                <p className="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    {slot.is_past ? 'Past' : slot.status === 'taken' ? 'Booked' : 'Open'}
                                                    {slot.active_requests_count > 0 && !slot.is_past && ` · ${slot.active_requests_count} request${slot.active_requests_count === 1 ? '' : 's'}`}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 gap-1.5">
                                                {slot.is_editable && (
                                                    <button type="button" onClick={() => startEdit(slot)} aria-label="Edit slot" className="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground hover:border-primary/40 hover:text-primary"><Pencil className="h-3.5 w-3.5" /></button>
                                                )}
                                                {slot.is_deletable && (
                                                    <button type="button" onClick={() => remove(slot)} aria-label="Delete slot" className="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"><Trash2 className="h-3.5 w-3.5" /></button>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Add slot */}
                    <form onSubmit={submitCreate} className="surface p-5 sm:p-6">
                        <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">
                            <Plus className="h-4 w-4 text-primary" /> Add a viewing slot
                        </h3>
                        <p className="mt-1 text-xs text-muted-foreground">Pick a day on the calendar to prefill it, then adjust the time.</p>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="field-label">Starts at</label>
                                <input type="datetime-local" value={create.data.starts_at} onChange={(e) => create.setData('starts_at', e.target.value)} className="field w-full" />
                                {create.errors.starts_at && <p className="mt-1 text-xs font-semibold text-rose-600">{create.errors.starts_at}</p>}
                            </div>
                            <div>
                                <label className="field-label">Ends at</label>
                                <input type="datetime-local" value={create.data.ends_at} onChange={(e) => create.setData('ends_at', e.target.value)} className="field w-full" />
                                {create.errors.ends_at && <p className="mt-1 text-xs font-semibold text-rose-600">{create.errors.ends_at}</p>}
                            </div>
                        </div>
                        <button type="submit" disabled={create.processing} className="btn-primary mt-4 h-11 w-full text-sm">
                            {create.processing ? 'Adding…' : 'Add slot'}
                        </button>
                    </form>
                </section>
            </div>

            {slots.length === 0 && requests.length === 0 && (
                <div className="mt-6">
                    <EmptyState icon={CalendarDays} title="No viewing times yet" description="Click a day on the calendar and add a slot — tenants can then book it, or suggest their own time." />
                </div>
            )}
        </MainLayout>
    );
}
