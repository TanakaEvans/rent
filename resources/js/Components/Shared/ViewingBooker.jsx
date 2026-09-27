import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CalendarClock, Clock, Plus } from 'lucide-react';
import Calendar from '@/Components/Shared/Calendar';
import { cn } from '@/lib/utils';

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const timeOf = (value) => new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
const longDay = (d) => d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });

/**
 * Tenant viewing booker: pick a day with open slots on a calendar and choose a
 * time, or suggest your own time when nothing suits. Posts to the existing
 * tenant.viewings.store / tenant.viewings.propose routes.
 */
export default function ViewingBooker({ propertyId, slots = [] }) {
    const openSlots = useMemo(
        () => slots.filter((s) => s.status !== 'taken' && new Date(s.ends_at) > new Date()).sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at)),
        [slots],
    );
    const firstDay = openSlots.length ? new Date(openSlots[0].starts_at) : null;
    const [selectedDay, setSelectedDay] = useState(firstDay);
    const [mode, setMode] = useState('slot'); // 'slot' | 'suggest'

    const book = useForm({ property_id: propertyId, slot_id: '', request_message: '' });
    const suggest = useForm({ property_id: propertyId, starts_at: '', ends_at: '', request_message: '' });

    const events = openSlots.map((s) => ({ id: s.id, start: s.starts_at, tone: 'green' }));
    const daySlots = selectedDay ? openSlots.filter((s) => ymd(new Date(s.starts_at)) === ymd(selectedDay)) : [];

    const submitBook = (e) => {
        e.preventDefault();
        book.post(route('tenant.viewings.store'), { preserveScroll: true, onSuccess: () => book.reset('slot_id', 'request_message') });
    };
    const submitSuggest = (e) => {
        e.preventDefault();
        suggest.post(route('tenant.viewings.propose'), { preserveScroll: true, onSuccess: () => suggest.reset('starts_at', 'ends_at', 'request_message') });
    };

    return (
        <div className="rounded-2xl border border-dashed border-primary/25 bg-primary/[0.03] p-3.5">
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary">
                    <CalendarClock className="h-4 w-4" /> Book a viewing
                </span>
                <div className="flex rounded-lg bg-muted p-0.5 text-[11px] font-bold">
                    <button type="button" onClick={() => setMode('slot')} className={cn('rounded-md px-2.5 py-1 transition', mode === 'slot' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground')}>Open times</button>
                    <button type="button" onClick={() => setMode('suggest')} className={cn('rounded-md px-2.5 py-1 transition', mode === 'suggest' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground')}>Suggest a time</button>
                </div>
            </div>

            {mode === 'slot' ? (
                openSlots.length === 0 ? (
                    <p className="mt-3 rounded-xl bg-muted/60 px-3 py-3 text-xs text-muted-foreground">
                        The owner hasn't opened any viewing times yet. Use <button type="button" onClick={() => setMode('suggest')} className="font-bold text-primary underline">Suggest a time</button> to propose one.
                    </p>
                ) : (
                    <form onSubmit={submitBook} className="mt-3 space-y-3">
                        <Calendar
                            events={events}
                            selected={selectedDay}
                            initialMonth={firstDay}
                            minDate={new Date()}
                            onSelectDay={(d) => { setSelectedDay(d); book.setData('slot_id', ''); }}
                            className="rounded-xl bg-white p-3"
                        />
                        {selectedDay && (
                            <div>
                                <p className="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{longDay(selectedDay)}</p>
                                {daySlots.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">No open times this day — pick a day with a green dot.</p>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {daySlots.map((slot) => (
                                            <button
                                                key={slot.id}
                                                type="button"
                                                onClick={() => book.setData('slot_id', String(slot.id))}
                                                className={cn('inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold transition',
                                                    String(book.data.slot_id) === String(slot.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-white text-foreground hover:border-primary/40')}
                                            >
                                                <Clock className="h-3.5 w-3.5" /> {timeOf(slot.starts_at)}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                        <input type="text" value={book.data.request_message} onChange={(e) => book.setData('request_message', e.target.value)} placeholder="Anything about the visit (optional)" maxLength={1000} className="field w-full" />
                        {['slot_id', 'request_message'].map((f) => book.errors[f] && <p key={f} className="text-xs font-semibold text-rose-600">{book.errors[f]}</p>)}
                        <button type="submit" disabled={book.processing || !book.data.slot_id} className="btn-primary h-11 w-full text-sm disabled:opacity-50">
                            {book.processing ? 'Requesting…' : 'Request this time'}
                        </button>
                    </form>
                )
            ) : (
                <form onSubmit={submitSuggest} className="mt-3 space-y-3">
                    <p className="text-xs text-muted-foreground">Propose a time that works for you. The owner confirms it or offers an alternative.</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div>
                            <label className="field-label">From</label>
                            <input type="datetime-local" value={suggest.data.starts_at} onChange={(e) => suggest.setData('starts_at', e.target.value)} className="field w-full" />
                        </div>
                        <div>
                            <label className="field-label">To</label>
                            <input type="datetime-local" value={suggest.data.ends_at} onChange={(e) => suggest.setData('ends_at', e.target.value)} className="field w-full" />
                        </div>
                    </div>
                    <input type="text" value={suggest.data.request_message} onChange={(e) => suggest.setData('request_message', e.target.value)} placeholder="Anything about the visit (optional)" maxLength={1000} className="field w-full" />
                    {['starts_at', 'ends_at', 'request_message'].map((f) => suggest.errors[f] && <p key={f} className="text-xs font-semibold text-rose-600">{suggest.errors[f]}</p>)}
                    <button type="submit" disabled={suggest.processing || !suggest.data.starts_at || !suggest.data.ends_at} className="btn-primary h-11 w-full text-sm disabled:opacity-50">
                        <Plus className="h-4 w-4" /> {suggest.processing ? 'Sending…' : 'Suggest this time'}
                    </button>
                </form>
            )}
        </div>
    );
}
