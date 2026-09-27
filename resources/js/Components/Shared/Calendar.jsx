import { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const ymd = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

/**
 * A month calendar. `events` are [{ id, start, end, tone, label }]; `start`
 * is anything Date can parse. Days with events show a count dot in the event
 * tone. Clicking a day calls `onSelectDay(Date)`; the `selected` date (Date or
 * null) is highlighted. Purely presentational and dependency-free.
 */
export default function Calendar({ events = [], selected = null, onSelectDay, minDate, initialMonth, className }) {
    const first = initialMonth ? new Date(initialMonth) : (selected ? new Date(selected) : new Date());
    const [cursor, setCursor] = useState(new Date(first.getFullYear(), first.getMonth(), 1));

    const today = startOfDay(new Date());
    const floor = minDate ? startOfDay(new Date(minDate)) : null;

    const eventsByDay = useMemo(() => {
        const map = {};
        for (const event of events) {
            const key = ymd(new Date(event.start));
            (map[key] ||= []).push(event);
        }
        return map;
    }, [events]);

    const grid = useMemo(() => {
        const firstOfMonth = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const offset = (firstOfMonth.getDay() + 6) % 7; // Monday-first
        const cells = [];
        for (let i = 0; i < 42; i++) {
            const date = new Date(cursor.getFullYear(), cursor.getMonth(), 1 - offset + i);
            cells.push(date);
        }
        return cells;
    }, [cursor]);

    const move = (delta) => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + delta, 1));

    return (
        <div className={cn('select-none', className)}>
            <div className="mb-3 flex items-center justify-between">
                <h4 className="text-sm font-bold text-foreground">{MONTHS[cursor.getMonth()]} {cursor.getFullYear()}</h4>
                <div className="flex items-center gap-1">
                    <button type="button" onClick={() => move(-1)} aria-label="Previous month" className="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground transition hover:border-primary/40 hover:text-primary">
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <button type="button" onClick={() => setCursor(new Date(today.getFullYear(), today.getMonth(), 1))} className="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-muted-foreground transition hover:border-primary/40 hover:text-primary">
                        Today
                    </button>
                    <button type="button" onClick={() => move(1)} aria-label="Next month" className="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground transition hover:border-primary/40 hover:text-primary">
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                {WEEKDAYS.map((d) => <div key={d} className="py-1">{d}</div>)}
            </div>

            <div className="mt-1 grid grid-cols-7 gap-1">
                {grid.map((date) => {
                    const inMonth = date.getMonth() === cursor.getMonth();
                    const key = ymd(date);
                    const dayEvents = eventsByDay[key] || [];
                    const isToday = key === ymd(today);
                    const isSelected = selected && key === ymd(new Date(selected));
                    const disabled = floor && startOfDay(date) < floor;
                    const tones = [...new Set(dayEvents.map((e) => e.tone || 'primary'))];

                    return (
                        <button
                            key={key}
                            type="button"
                            disabled={disabled || !onSelectDay}
                            onClick={() => onSelectDay?.(startOfDay(date))}
                            className={cn(
                                'relative flex aspect-square flex-col items-center justify-center rounded-xl text-sm transition',
                                inMonth ? 'text-foreground' : 'text-muted-foreground/40',
                                disabled && 'cursor-not-allowed opacity-40',
                                !disabled && onSelectDay && 'hover:bg-muted',
                                isSelected && 'bg-primary text-primary-foreground hover:bg-primary',
                                !isSelected && isToday && 'ring-1 ring-inset ring-primary/40',
                            )}
                        >
                            <span className={cn('font-semibold', isToday && !isSelected && 'text-primary')}>{date.getDate()}</span>
                            {dayEvents.length > 0 && (
                                <span className="absolute bottom-1 flex gap-0.5">
                                    {tones.slice(0, 3).map((tone) => (
                                        <span
                                            key={tone}
                                            className={cn('h-1.5 w-1.5 rounded-full', isSelected ? 'bg-white/80' : {
                                                primary: 'bg-primary',
                                                green: 'bg-green-500',
                                                amber: 'bg-amber-500',
                                                slate: 'bg-slate-400',
                                            }[tone] || 'bg-primary')}
                                        />
                                    ))}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
