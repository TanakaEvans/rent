import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Wrench, Clock3, TriangleAlert, HardHat, BadgeDollarSign, BadgeCheck, CheckCircle2, Star } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';

const priorityPill = {
    low: 'border-slate-200 bg-slate-50 text-slate-600',
    medium: 'border-amber-200 bg-amber-50 text-amber-700',
    high: 'border-rose-200 bg-rose-50 text-rose-700',
    emergency: 'border-orange-200 bg-orange-50 text-orange-700',
};

const categoryLabel = {
    plumbing: 'Plumbing',
    electrical: 'Electrical',
    appliance: 'Appliance',
    structural: 'Structural',
    pest: 'Pest control',
    safety: 'Safety',
    other: 'Other',
};

const timeLeft = (sla) => {
    if (!sla) return null;
    const seconds = Math.round((new Date(sla).getTime() - Date.now()) / 1000);
    const hours = Math.abs(seconds) / 3600;
    if (seconds <= 0) {
        return { overdue: true, label: hours >= 24 ? `${Math.floor(hours / 24)}d overdue` : `${Math.ceil(hours)}h overdue` };
    }
    return { overdue: false, label: hours >= 24 ? `${Math.floor(hours / 24)}d left` : `${Math.max(1, Math.ceil(hours))}h left` };
};

const priorityRank = { emergency: 0, high: 1, medium: 2, low: 3 };
const sorted = (requests) =>
    [...requests].sort((a, b) => {
        const aBreach = a.escalated_at ? 0 : 1;
        const bBreach = b.escalated_at ? 0 : 1;
        if (aBreach !== bBreach) return aBreach - bBreach;
        return (priorityRank[a.priority] ?? 4) - (priorityRank[b.priority] ?? 4);
    });

function AssignForm({ request, contractors }) {
    const [contractorId, setContractorId] = useState('');
    const [quote, setQuote] = useState('');

    const submit = () => {
        router.post(
            route('owner.maintenance.assign', { maintenanceRequest: request.id }),
            { contractor_id: contractorId, approved_quote: quote },
            { preserveScroll: true }
        );
    };

    return (
        <div className="mt-4 rounded-xl border border-border bg-muted/40 p-3.5">
            <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">Assign a contractor</p>
            <div className="grid gap-2.5 sm:grid-cols-[1fr_9rem_auto]">
                <select className="field" value={contractorId} onChange={(e) => setContractorId(e.target.value)}>
                    <option value="">Choose a verified contractor…</option>
                    {contractors.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.business_name} — {(c.service_area || []).join(', ')} · ★ {c.rating_avg} ({c.jobs_completed} jobs)
                        </option>
                    ))}
                </select>
                <input className="field" type="number" min="0" step="0.01" value={quote} onChange={(e) => setQuote(e.target.value)} placeholder="Approved quote $" />
                <Button onClick={submit} disabled={!contractorId || quote === '' || Number(quote) < 0}>
                    Assign
                </Button>
            </div>
            {contractors.length === 0 && (
                <p className="mt-2 text-xs font-semibold text-amber-600">
                    No verified contractors yet — the platform registers and verifies tradespeople before they appear here.
                </p>
            )}
        </div>
    );
}

function CloseForm({ request }) {
    const [notes, setNotes] = useState('');

    const submit = () => {
        router.post(
            route('owner.maintenance.close', { maintenanceRequest: request.id }),
            { notes: notes.trim() || null },
            { preserveScroll: true }
        );
    };

    return (
        <div className="mt-4 rounded-xl border border-teal-200 bg-teal-50/50 p-3.5">
            <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-teal-800">Inspect & close</p>
            <div className="grid gap-2.5 sm:grid-cols-[1fr_auto]">
                <input className="field" value={notes} maxLength={500} onChange={(e) => setNotes(e.target.value)} placeholder="Inspection note (optional)" />
                <Button onClick={submit} variant="outline" className="border-teal-300 text-teal-800">
                    <CheckCircle2 className="h-4 w-4" /> Close request
                </Button>
            </div>
        </div>
    );
}

function RateForm({ request }) {
    const [score, setScore] = useState(0);
    const [note, setNote] = useState('');

    const submit = () => {
        router.post(
            route('owner.maintenance.rate', { maintenanceRequest: request.id }),
            { rating: score, note: note.trim() || null },
            { preserveScroll: true }
        );
    };

    return (
        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-3.5">
            <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-amber-800">Rate the contractor</p>
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => setScore(n)}
                        className="rounded-lg p-1 transition hover:scale-110"
                        aria-label={`${n} out of 5`}
                    >
                        <Star className={`h-6 w-6 ${n <= score ? 'fill-amber-400 text-amber-400' : 'text-slate-300'}`} />
                    </button>
                ))}
            </div>
            <div className="mt-3 grid gap-2.5 sm:grid-cols-[1fr_auto]">
                <input className="field" value={note} maxLength={500} onChange={(e) => setNote(e.target.value)} placeholder="Feedback for the contractor (optional)" />
                <Button onClick={submit} disabled={score === 0} variant="outline" className="border-amber-300 text-amber-800">
                    Submit rating
                </Button>
            </div>
        </div>
    );
}

export default function OwnerMaintenance({ requests = {}, contractors = [] }) {
    const items = requests.data || [];
    const reportable = items.filter((r) => ['reported', 'assigned', 'in_progress', 'completed'].includes(r.status));
    const open = items.filter((r) => r.status === 'reported');
    const breached = open.filter((r) => r.escalated_at);
    const done = items.filter((r) => ['closed', 'declined'].includes(r.status));

    return (
        <MainLayout title="Maintenance">
            <Head title="Maintenance" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Maintenance</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Repair requests from your tenants, triaged by priority. Requests past their first-response SLA escalate to the platform team.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Wrench} label="Open requests" value={String(reportable.length)} hint="Awaiting action" tone="emerald" />
                <StatCard icon={TriangleAlert} label="SLA breaches" value={String(breached.length)} hint="Escalated to staff" tone="amber" />
                <StatCard icon={HardHat} label="Awaiting assignment" value={String(open.length)} hint="Pick a verified contractor" tone="sky" />
                <StatCard icon={Wrench} label="Closed" value={String(done.length)} hint="Completed or declined" tone="teal" />
            </div>

            <h3 className="mb-3 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">Repair queue</h3>

            {reportable.length === 0 ? (
                <EmptyState
                    icon={Wrench}
                    title="No open requests"
                    description="When a tenant reports a fault in one of your rented homes it will land here for triage."
                />
            ) : (
                <div className="space-y-4">
                    {sorted(reportable).map((r) => {
                        const left = timeLeft(r.sla_due_at);
                        return (
                            <article key={r.id} className={`surface p-5 ${r.priority === 'emergency' || r.escalated_at ? 'ring-1 ring-rose-200' : ''}`}>
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h4 className="font-extrabold tracking-tight text-foreground">{r.title}</h4>
                                            <StatusBadge status={r.status} />
                                        </div>
                                        <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                            {r.request_no} · {categoryLabel[r.category] || r.category} · {r.property?.title || '—'}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold capitalize ${priorityPill[r.priority] || priorityPill.medium}`}>
                                            {r.priority}
                                        </span>
                                        {r.escalated_at && (
                                            <span className="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-[11px] font-bold text-orange-700">
                                                <TriangleAlert className="h-3.5 w-3.5" /> Escalated
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{r.description}</p>
                                <div className="mt-4 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                    <span className="font-semibold">Reported by {r.tenant?.name} on {new Date(r.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                    {r.status === 'reported' && left && (
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-bold ${left.overdue ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-muted/60'}`}>
                                            <Clock3 className="h-3.5 w-3.5" /> First response: {left.label}
                                        </span>
                                    )}
                                </div>
                                {r.status === 'assigned' && r.contractor && (
                                    <div className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3.5 py-2 text-xs font-semibold text-sky-800">
                                        <HardHat className="h-4 w-4" /> {r.contractor.business_name}
                                        {r.approved_quote && (
                                            <span className="inline-flex items-center gap-1 text-emerald-700">
                                                <BadgeDollarSign className="h-4 w-4" /> Quote ${r.approved_quote}
                                            </span>
                                        )}
                                        <span className="text-sky-600">— job brief sent</span>
                                    </div>
                                )}
                                {r.status === 'completed' && (
                                    r.tenant_confirmed_at ? (
                                        <>
                                            <div className="mt-4 inline-flex items-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-3.5 py-2 text-xs font-semibold text-teal-800">
                                                <BadgeCheck className="h-4 w-4" /> Tenant confirmed the fix
                                            </div>
                                            <CloseForm request={r} />
                                        </>
                                    ) : (
                                        <p className="mt-4 text-xs font-semibold text-muted-foreground">
                                            Waiting for the tenant to confirm the fix before this request can be closed.
                                        </p>
                                    )
                                )}
                                {r.status === 'reported' && <AssignForm request={r} contractors={contractors} />}
                            </article>
                        );
                    })}
                </div>
            )}

            <h3 className="mb-3 mt-10 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">Recently closed</h3>

            {done.length === 0 ? (
                <EmptyState
                    icon={CheckCircle2}
                    title="Nothing closed yet"
                    description="Requests you close will appear here so you can rate the contractor behind each job."
                />
            ) : (
                <div className="space-y-4">
                    {done.map((r) => (
                        <article key={r.id} className="surface p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h4 className="font-extrabold tracking-tight text-foreground">{r.title}</h4>
                                        <StatusBadge status={r.status} />
                                    </div>
                                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {r.request_no} · {categoryLabel[r.category] || r.category} · {r.property?.title || '—'}
                                    </div>
                                </div>
                            </div>
                            {r.contractor && (
                                <div className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3.5 py-2 text-xs font-semibold text-sky-800">
                                    <HardHat className="h-4 w-4" /> {r.contractor.business_name}
                                    {r.approved_quote && (
                                        <span className="inline-flex items-center gap-1 text-emerald-700">
                                            <BadgeDollarSign className="h-4 w-4" /> Quote ${r.approved_quote}
                                        </span>
                                    )}
                                </div>
                            )}
                            {r.status === 'closed' && r.contractor ? (
                                r.rating ? (
                                    <div className="mt-4 inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-800">
                                        <Star className="h-4 w-4 fill-amber-400 text-amber-400" /> You rated {r.rating.rating}/5
                                        {r.rating.note && <span className="font-normal text-amber-700"> — {r.rating.note}</span>}
                                    </div>
                                ) : (
                                    <RateForm request={r} />
                                )
                            ) : (
                                <p className="mt-4 text-xs font-semibold text-muted-foreground">
                                    {r.status === 'declined' ? 'Declined — no contractor was assigned.' : 'Closed.'}
                                </p>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}