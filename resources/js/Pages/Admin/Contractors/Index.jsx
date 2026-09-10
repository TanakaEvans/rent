import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Briefcase, Plus, Trash2, Star, BadgeCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const statusLabel = {
    unverified: 'Start vetting',
    vetting: 'Verify',
    verified: 'Suspend',
    suspended: 'Reinstate',
};

export default function ContractorRegistry({ contractors = {}, errors = {} }) {
    const items = contractors.data || [];
    const verified = items.filter((c) => c.status === 'verified');
    const underReview = items.filter((c) => ['unverified', 'vetting'].includes(c.status));

    const [businessName, setBusinessName] = useState('');
    const [contact, setContact] = useState('');
    const [serviceArea, setServiceArea] = useState('');
    const [trades, setTrades] = useState([{ trade: '', rate: '' }]);

    const addTrade = () => setTrades([...trades, { trade: '', rate: '' }]);
    const updateTrade = (index, key, value) =>
        setTrades(trades.map((t, i) => (i === index ? { ...t, [key]: value } : t)));

    const submit = (e) => {
        e.preventDefault();
        router.post(
            route('admin.contractors.store'),
            {
                business_name: businessName,
                contact,
                service_area: serviceArea.split(',').map((s) => s.trim()).filter(Boolean),
                trades,
            },
            { preserveScroll: true }
        );
    };

    const move = (id, status) => {
        router.post(route('admin.contractors.status', { contractor: id }), { status }, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Contractor Registry">
            <Head title="Contractor Registry" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Contractor Registry</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Verified tradespeople owners can hire for maintenance jobs. New profiles land in vetting; only verified contractors appear in the owner assign list.
                </p>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard icon={Briefcase} label="On registry" value={String(items.length)} hint="Registered profiles" tone="teal" />
                <StatCard icon={BadgeCheck} label="Verified" value={String(verified.length)} hint="Assignable to jobs" tone="emerald" />
                <StatCard icon={Star} label="Under review" value={String(underReview.length)} hint="Vetting or awaiting review" tone="amber" />
            </div>

            <div className="mb-8 grid gap-6 lg:grid-cols-5">
                <form onSubmit={submit} className="surface h-fit p-5 lg:col-span-2">
                    <h3 className="mb-4 font-extrabold tracking-tight text-foreground">Register tradesperson</h3>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="business_name">Business name</Label>
                            <Input id="business_name" value={businessName} onChange={(e) => setBusinessName(e.target.value)} placeholder="e.g. Bulawayo Plumbing Co." />
                            {errors.business_name && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.business_name}</p>}
                        </div>
                        <div>
                            <Label htmlFor="contact">Contact</Label>
                            <Input id="contact" value={contact} onChange={(e) => setContact(e.target.value)} placeholder="Phone · email" />
                            {errors.contact && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.contact}</p>}
                        </div>
                        <div>
                            <Label htmlFor="service_area">Service areas (comma separated)</Label>
                            <Input id="service_area" value={serviceArea} onChange={(e) => setServiceArea(e.target.value)} placeholder="Bulawayo, Gweru" />
                            {errors.service_area && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.service_area}</p>}
                        </div>
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <Label>Trades</Label>
                                <button type="button" onClick={addTrade} className="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                    <Plus className="h-3.5 w-3.5" /> Add
                                </button>
                            </div>
                            <div className="space-y-2">
                                {trades.map((t, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <Input value={t.trade} onChange={(e) => updateTrade(i, 'trade', e.target.value)} placeholder="Trade (e.g. Plumbing)" className="flex-1" />
                                        <Input value={t.rate} onChange={(e) => updateTrade(i, 'rate', e.target.value)} placeholder="Rate $" className="w-24" />
                                        <button type="button" onClick={() => setTrades(trades.filter((_, j) => j !== i))} className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-rose-50 hover:text-rose-600" aria-label="Remove">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                            {errors.trades && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.trades}</p>}
                        </div>
                    </div>
                    <Button type="submit" className="mt-5 w-full">
                        Register (into vetting)
                    </Button>
                </form>

                <div className="lg:col-span-3">
                    <h3 className="mb-3 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">Registry</h3>
                    {items.length === 0 ? (
                        <EmptyState
                            icon={Briefcase}
                            title="No contractors yet"
                            description="Register a tradesperson on the left to start building the verified registry."
                        />
                    ) : (
                        <div className="space-y-4">
                            {items.map((c) => (
                                <article key={c.id} className="surface p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-extrabold tracking-tight text-foreground">{c.business_name}</h4>
                                                <StatusBadge status={c.status} />
                                            </div>
                                            <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                                {c.contact} · {(c.service_area || []).join(', ') || 'No areas'}
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            <span className="inline-flex items-center gap-1 text-xs font-bold text-amber-600">
                                                <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> {c.rating_avg} · {c.jobs_completed} jobs
                                            </span>
                                            {c.user?.name && (
                                                <span className="text-[11px] font-semibold text-muted-foreground">Linked login: {c.user.name}</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {(c.trades || []).map((t) => (
                                            <span key={t.id} className="rounded-full border border-border bg-muted/60 px-2.5 py-1 text-[11px] font-semibold text-foreground">
                                                {t.trade}{t.rate ? ` · $${t.rate}` : ''}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="mt-4 flex items-center justify-between gap-3">
                                        <p className="text-xs text-muted-foreground">
                                            {c.assignments_count > 0 ? `${c.assignments_count} active job${c.assignments_count === 1 ? '' : 's'} assigned` : 'No jobs assigned yet'}
                                        </p>
                                        <Button size="sm" variant={c.status === 'verified' ? 'outline' : 'default'} onClick={() => move(c.id, statusLabel[c.status])}>
                                            {statusLabel[c.status]}
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}