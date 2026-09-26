import { Head, router } from '@inertiajs/react';
import { TriangleAlert, ClipboardCheck, Siren, UserCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import Pagination from '@/Components/Pagination';
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

const formatDate = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

function RequestCard({ request, footer, onAck }) {
    return (
        <article className={`surface p-5 ${request.priority === 'emergency' ? 'ring-1 ring-rose-200' : ''}`}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="font-extrabold tracking-tight text-foreground">{request.title}</h4>
                        <StatusBadge status={request.status} />
                    </div>
                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                        {request.request_no} · {categoryLabel[request.category] || request.category} · {request.property?.title || '—'}
                    </div>
                </div>
                <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold capitalize ${priorityPill[request.priority] || priorityPill.medium}`}>
                    {request.priority}
                </span>
            </div>
            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{request.description}</p>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs font-semibold text-muted-foreground">
                    Reported by {request.tenant?.name} on {formatDate(request.created_at)}
                    {' · '}
                    {footer}
                </p>
                {onAck && (
                    <Button size="sm" onClick={() => onAck(request.id)}>
                        Take ownership
                    </Button>
                )}
            </div>
        </article>
    );
}

function Section({ id, title, description, children }) {
    return (
        <section id={id} className="mb-10 scroll-mt-24">
            <h3 className="px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">{title}</h3>
            <p className="mb-3 px-1 text-xs text-muted-foreground">{description}</p>
            {children}
        </section>
    );
}

export default function MaintenanceEscalations({ requests = {}, emergencies = {}, acknowledged = {}, errors = {} }) {
    const escalatedItems = requests.data || [];
    const emergencyItems = emergencies.data || [];
    const acknowledgedItems = acknowledged.data || [];

    const ack = (id) => {
        router.post(route('admin.maintenance.escalations.ack', { maintenanceRequest: id }), {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Maintenance Escalations">
            <Head title="Maintenance Escalations" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Maintenance Escalations</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Emergency reports land here the moment they are filed; other requests arrive once the daily sweep finds their first-response SLA breached.
                    Take ownership to move a request to Acknowledged — it stays there until the owner assigns a contractor and is never re-escalated.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                <StatCard icon={Siren} label="Live emergencies" value={String(emergencies.total ?? 0)} hint="Inside SLA, awaiting staff" tone="rose" />
                <StatCard icon={TriangleAlert} label="SLA breaches" value={String(requests.total ?? 0)} hint="Breached first-response SLA" tone="amber" />
                <StatCard icon={ClipboardCheck} label="Acknowledged" value={String(acknowledged.total ?? 0)} hint="Owned by staff, awaiting assignment" tone="teal" />
            </div>

            {errors.request && (
                <p className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{errors.request}</p>
            )}

            <Section id="emergencies" title="Emergencies" description="Emergency reports still inside their first-response SLA.">
                {emergencyItems.length === 0 ? (
                    <EmptyState icon={Siren} title="No live emergencies" description="New emergency reports appear here immediately." />
                ) : (
                    <div className="space-y-4">
                        {emergencyItems.map((r) => (
                            <RequestCard key={r.id} request={r} footer={`SLA due ${formatDate(r.sla_due_at)}`} onAck={ack} />
                        ))}
                        <Pagination data={emergencies} />
                    </div>
                )}
            </Section>

            <Section id="breaches" title="SLA breaches" description="Requests the daily sweep escalated after their first-response SLA passed.">
                {escalatedItems.length === 0 ? (
                    <EmptyState
                        icon={TriangleAlert}
                        title="No escalations"
                        description="Every open request is within its first-response SLA. The daily sweep alerts you the moment one breaches."
                    />
                ) : (
                    <div className="space-y-4">
                        {escalatedItems.map((r) => (
                            <RequestCard key={r.id} request={r} footer={`SLA breached ${formatDate(r.escalated_at)}`} onAck={ack} />
                        ))}
                        <Pagination data={requests} />
                    </div>
                )}
            </Section>

            <Section id="acknowledged" title="Acknowledged" description="Requests a staff member owns. They leave this list once the owner assigns a contractor.">
                {acknowledgedItems.length === 0 ? (
                    <EmptyState icon={UserCheck} title="Nothing acknowledged" description="Requests you take ownership of are tracked here until they are assigned." />
                ) : (
                    <div className="space-y-4">
                        {acknowledgedItems.map((r) => (
                            <RequestCard
                                key={r.id}
                                request={r}
                                footer={`Owned by ${r.acknowledgement?.actor?.name || 'staff'} since ${formatDate(r.acknowledgement?.created_at)}`}
                            />
                        ))}
                        <Pagination data={acknowledged} />
                    </div>
                )}
            </Section>
        </AdminLayout>
    );
}
