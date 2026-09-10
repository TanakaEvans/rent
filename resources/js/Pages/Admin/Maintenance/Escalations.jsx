import { Head, router } from '@inertiajs/react';
import { TriangleAlert, ClipboardCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';

const categoryLabel = {
    plumbing: 'Plumbing',
    electrical: 'Electrical',
    appliance: 'Appliance',
    structural: 'Structural',
    pest: 'Pest control',
    safety: 'Safety',
    other: 'Other',
};

const priorityPill = {
    low: 'border-slate-200 bg-slate-50 text-slate-600',
    medium: 'border-amber-200 bg-amber-50 text-amber-700',
    high: 'border-rose-200 bg-rose-50 text-rose-700',
    emergency: 'border-orange-200 bg-orange-50 text-orange-700',
};

export default function MaintenanceEscalations({ requests = {} }) {
    const items = requests.data || [];
    const emergencies = items.filter((r) => r.priority === 'emergency');

    const ack = (id) => {
        router.post(route('admin.maintenance.escalations.ack', { maintenanceRequest: id }), {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Maintenance Escalations">
            <Head title="Maintenance Escalations" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Maintenance Escalations</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Requests that breached their first-response SLA and were escalated by the daily sweep. Acknowledge one to take ownership — it drops back into the owner queue.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2">
                <StatCard icon={TriangleAlert} label="Awaiting staff" value={String(items.length)} hint="Breached first-response SLA" tone="amber" />
                <StatCard icon={ClipboardCheck} label="Emergency breaches" value={String(emergencies.length)} hint="Immediate risk" tone="rose" />
            </div>

            {items.length === 0 ? (
                <EmptyState
                    icon={TriangleAlert}
                    title="No escalations"
                    description="Every open request is within its first-response SLA. The daily sweep alerts you the moment one breaches."
                />
            ) : (
                <div className="space-y-4">
                    {items.map((r) => (
                        <article key={r.id} className={`surface p-5 ${r.priority === 'emergency' ? 'ring-1 ring-rose-200' : ''}`}>
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
                                <div className="flex flex-col items-end gap-2">
                                    <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold capitalize ${priorityPill[r.priority] || priorityPill.medium}`}>
                                        {r.priority}
                                    </span>
                                </div>
                            </div>
                            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{r.description}</p>
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-xs font-semibold text-muted-foreground">
                                    Reported by {r.tenant?.name} on {r.created_at ? new Date(r.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                                    {' · '}SLA breached {r.escalated_at ? new Date(r.escalated_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                                </p>
                                <Button size="sm" onClick={() => ack(r.id)}>
                                    Take ownership
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}