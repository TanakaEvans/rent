import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Wrench, Clock3, TriangleAlert, CheckCircle2, Send, BadgeCheck } from 'lucide-react';
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

export default function TenantMaintenance({ requests = {}, properties = [], categories = [] }) {
    const items = requests.data || [];
    const [propertyId, setPropertyId] = useState('');
    const [category, setCategory] = useState('');
    const [priority, setPriority] = useState('medium');
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');

    const open = items.filter((r) => r.status === 'reported');
    const closed = items.filter((r) => ['closed', 'declined'].includes(r.status));
    const escalated = items.filter((r) => r.escalated_at);

    const submit = () => {
        if (!propertyId || !category || !title.trim() || !description.trim()) return;
        router.post(
            route('tenant.maintenance.store'),
            { property_id: propertyId, category, priority, title, description },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPropertyId('');
                    setCategory('');
                    setPriority('medium');
                    setTitle('');
                    setDescription('');
                },
            }
        );
    };

    return (
        <MainLayout title="Maintenance">
            <Head title="Maintenance" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Maintenance</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Report a fault in your rented home. Emergency issues page your landlord and the platform team immediately.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Wrench} label="Open requests" value={String(open.length)} hint="Awaiting the owner" tone="emerald" />
                <StatCard icon={Clock3} label="SLA on the clock" value={String(open.filter((r) => !timeLeft(r.sla_due_at)?.overdue && r.sla_due_at).length)} hint="Within first-response window" tone="sky" />
                <StatCard icon={TriangleAlert} label="Escalated to staff" value={String(escalated.length)} hint="Past first-response SLA" tone="amber" />
                <StatCard icon={CheckCircle2} label="Closed" value={String(closed.length)} hint="Completed or declined" tone="teal" />
            </div>

            <div className="grid gap-8 lg:grid-cols-5">
                <section className="surface h-fit p-6 lg:col-span-2">
                    <h3 className="mb-1 text-lg font-extrabold tracking-tight text-foreground">Report an issue</h3>
                    <p className="mb-5 text-sm text-muted-foreground">You can report faults on homes you currently lease.</p>

                    {properties.length === 0 ? (
                        <EmptyState
                            icon={Wrench}
                            title="No reportable homes"
                            description="You can only report maintenance on a home you have an active lease for."
                            className="bg-background rounded-xl"
                        />
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Home</label>
                                <select className="field" value={propertyId} onChange={(e) => setPropertyId(e.target.value)}>
                                    <option value="">Select a home…</option>
                                    {properties.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title} — {p.city}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Category</label>
                                    <select className="field" value={category} onChange={(e) => setCategory(e.target.value)}>
                                        <option value="">Choose…</option>
                                        {categories.map((c) => (
                                            <option key={c} value={c}>
                                                {categoryLabel[c] || c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Priority</label>
                                    <select className="field" value={priority} onChange={(e) => setPriority(e.target.value)}>
                                        <option value="low">Low — can wait</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High — disruptive</option>
                                        <option value="emergency">Emergency — urgent danger</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Short title</label>
                                <input className="field" value={title} maxLength={120} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Kitchen tap is leaking" />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">What's wrong?</label>
                                <textarea className="field min-h-28" value={description} maxLength={2000} onChange={(e) => setDescription(e.target.value)} placeholder="Describe the fault so the owner can triage it…" />
                            </div>

                            {priority === 'emergency' && (
                                <p className="rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">
                                    Emergency reports also alert the platform team and open a 24-hour first-response SLA.
                                </p>
                            )}

                            <Button onClick={submit} disabled={!propertyId || !category || !title.trim() || !description.trim()} className="w-full">
                                <Send className="h-4 w-4" /> Submit request
                            </Button>
                        </div>
                    )}
                </section>

                <section className="lg:col-span-3">
                    <h3 className="mb-3 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">My requests</h3>

                    {items.length === 0 ? (
                        <EmptyState
                            icon={Wrench}
                            title="No maintenance requests"
                            description="Issues you report will appear here with their status and SLA countdown."
                        />
                    ) : (
                        <div className="space-y-4">
                            {items.map((r) => {
                                const left = timeLeft(r.sla_due_at);
                                return (
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
                                        {r.status === 'reported' && left && (
                                            <p className={`mt-4 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold ${left.overdue ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-muted/60 text-muted-foreground'}`}>
                                                <Clock3 className="h-3.5 w-3.5" /> First response: {left.label}
                                            </p>
                                        )}
                                        {r.status === 'completed' && (
                                            r.tenant_confirmed_at ? (
                                                <p className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-[11px] font-bold text-teal-700 ring-1 ring-teal-200">
                                                    <BadgeCheck className="h-3.5 w-3.5" /> Fix confirmed — the owner will close once inspected
                                                </p>
                                            ) : (
                                                <div className="mt-4 flex flex-wrap items-center gap-3">
                                                    <Button size="sm" onClick={() => router.post(route('tenant.maintenance.confirm', { maintenanceRequest: r.id }), {}, { preserveScroll: true })}>
                                                        <CheckCircle2 className="h-4 w-4" /> Confirm the fix
                                                    </Button>
                                                    <span className="text-xs text-muted-foreground">Confirm once you're happy the issue is resolved.</span>
                                                </div>
                                            )
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </MainLayout>
    );
}