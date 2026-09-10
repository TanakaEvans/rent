import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Briefcase, BadgeDollarSign, MapPin, Phone, Wrench, Play, CheckCircle2, Star } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
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

export default function ContractorJobs({ requests = {}, profile = null }) {
    const items = requests.data || [];

    return (
        <MainLayout title="My Jobs">
            <Head title="My Jobs" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Jobs</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {profile
                        ? `${profile.business_name} — job briefs handed to you by property owners.`
                        : 'Jobs assigned to you by property owners appear here once your profile is linked to this login.'}
                </p>
                {profile && (Number(profile.jobs_completed) > 0 || Number(profile.rating_avg) > 0) && (
                    <p className="mt-2 inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">
                        <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> {profile.rating_avg} average · {profile.jobs_completed} completed jobs
                    </p>
                )}
            </div>

            {items.length === 0 ? (
                <EmptyState
                    icon={Briefcase}
                    title="No jobs assigned yet"
                    description="When an owner assigns you a maintenance brief it lands here with the job details and the approved quote."
                />
            ) : (
                <div className="space-y-4">
                    {items.map((r) => (
                        <article key={r.id} className="surface p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h4 className="font-extrabold tracking-tight text-foreground">{r.title}</h4>
                                        <StatusBadge status={r.status} />
                                    </div>
                                    <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {r.request_no} · {categoryLabel[r.category] || r.category}
                                    </div>
                                </div>
                                <div className="flex flex-col items-end gap-2">
                                    <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold capitalize ${priorityPill[r.priority] || priorityPill.medium}`}>
                                        {r.priority}
                                    </span>
                                    {r.approved_quote && (
                                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                            <BadgeDollarSign className="h-3.5 w-3.5" /> Approved quote ${r.approved_quote}
                                        </span>
                                    )}
                                    {r.status === 'completed' && r.tenant_confirmed_at && (
                                        <span className="inline-flex items-center gap-1 rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-700">
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Fix confirmed by the tenant
                                        </span>
                                    )}
                                </div>
                            </div>
                            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{r.description}</p>
                            <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5 font-semibold">
                                    <MapPin className="h-3.5 w-3.5" /> {r.property?.title || '—'}
                                    {r.property?.suburb ? `, ${r.property.suburb}` : ''}
                                </span>
                                {r.property?.owner && (
                                    <span className="inline-flex items-center gap-1.5 font-semibold">
                                        <Phone className="h-3.5 w-3.5" /> {r.property.owner.name}
                                    </span>
                                )}
                                <span className="inline-flex items-center gap-1.5 font-semibold">
                                    <Wrench className="h-3.5 w-3.5" /> Assigned {r.created_at ? new Date(r.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                                </span>
                            </div>
                            <JobActions request={r} />
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}

function JobActions({ request }) {
    const [notes, setNotes] = useState('');

    if (request.status === 'assigned') {
        return (
            <div className="mt-4">
                <Button size="sm" onClick={() => router.post(route('contractor.maintenance.start', { maintenanceRequest: request.id }), {}, { preserveScroll: true })}>
                    <Play className="h-4 w-4" /> Start job
                </Button>
            </div>
        );
    }

    if (request.status === 'in_progress') {
        return (
            <div className="mt-4 rounded-xl border border-border bg-muted/40 p-3.5">
                <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">Mark the work complete</p>
                <div className="grid gap-2.5 sm:grid-cols-[1fr_auto]">
                    <input
                        className="field"
                        value={notes}
                        maxLength={500}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Summarise what you fixed…"
                    />
                    <Button
                        onClick={() =>
                            router.post(
                                route('contractor.maintenance.complete', { maintenanceRequest: request.id }),
                                { notes },
                                { preserveScroll: true }
                            )
                        }
                        disabled={!notes.trim()}
                    >
                        <CheckCircle2 className="h-4 w-4" /> Complete
                    </Button>
                </div>
            </div>
        );
    }

    if (request.status === 'completed' && !request.tenant_confirmed_at) {
        return (
            <p className="mt-4 text-xs font-semibold text-muted-foreground">
                Waiting for the tenant to confirm the fix before this job can be closed.
            </p>
        );
    }

    return null;
}