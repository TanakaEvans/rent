import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Briefcase, Plus, Trash2, Star, BadgeCheck, Link2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

// Button label for moving a profile from its current status to a target status.
const actionLabel = (from, to) =>
    ({
        vetting: from === 'unverified' ? 'Start vetting' : 'Return to vetting',
        verified: from === 'suspended' ? 'Reinstate' : 'Verify',
        suspended: 'Suspend',
        unverified: 'Mark unverified',
    })[to] || to;

function LinkLoginForm({ contractor }) {
    const [email, setEmail] = useState('');

    const submit = (e) => {
        e.preventDefault();
        router.post(
            route('admin.contractors.link', { contractor: contractor.id }),
            { user_email: email.trim() },
            { preserveScroll: true, onSuccess: () => setEmail('') }
        );
    };

    return (
        <form onSubmit={submit} className="mt-3 flex flex-wrap items-center gap-2">
            <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="Login account email"
                aria-label={`Login account email for ${contractor.business_name}`}
                className="h-8 max-w-64 text-xs"
            />
            <Button type="submit" size="sm" variant="outline" disabled={!email.trim()}>
                <Link2 className="h-3.5 w-3.5" /> Link login
            </Button>
        </form>
    );
}

export default function ContractorRegistry({ contractors = {}, stats = {}, transitions = {}, errors = {} }) {
    const items = contractors.data || [];

    const [businessName, setBusinessName] = useState('');
    const [contact, setContact] = useState('');
    const [serviceArea, setServiceArea] = useState('');
    const [userEmail, setUserEmail] = useState('');
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
                user_email: userEmail.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBusinessName('');
                    setContact('');
                    setServiceArea('');
                    setUserEmail('');
                    setTrades([{ trade: '', rate: '' }]);
                },
            }
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
                <StatCard icon={Briefcase} label="On registry" value={String(stats.total ?? 0)} hint="Registered profiles" tone="teal" />
                <StatCard icon={BadgeCheck} label="Verified" value={String(stats.verified ?? 0)} hint="Assignable to jobs" tone="emerald" />
                <StatCard icon={Star} label="Under review" value={String(stats.under_review ?? 0)} hint="Vetting or awaiting review" tone="amber" />
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
                            <Label htmlFor="user_email">Login account (email, optional)</Label>
                            <Input id="user_email" type="email" value={userEmail} onChange={(e) => setUserEmail(e.target.value)} placeholder="contractor@example.com" />
                            <p className="mt-1 text-[11px] text-muted-foreground">Links an existing account and gives it the Contractor role so it can open My Jobs.</p>
                            {errors.user_email && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.user_email}</p>}
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
                    {errors.status && <p className="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{errors.status}</p>}
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
                                            <span className="text-[11px] font-semibold text-muted-foreground">
                                                {c.user ? `Linked login: ${c.user.name} (${c.user.email})` : 'No login linked'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {(c.trades || []).map((t) => (
                                            <span key={t.id} className="rounded-full border border-border bg-muted/60 px-2.5 py-1 text-[11px] font-semibold text-foreground">
                                                {t.trade}{t.rate ? ` · $${t.rate}` : ''}
                                            </span>
                                        ))}
                                    </div>
                                    {!c.user && <LinkLoginForm contractor={c} />}
                                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                        <p className="text-xs text-muted-foreground">
                                            {c.assignments_count > 0 ? `${c.assignments_count} job${c.assignments_count === 1 ? '' : 's'} assigned` : 'No jobs assigned yet'}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {(transitions[c.status] || []).map((target) => (
                                                <Button
                                                    key={target}
                                                    size="sm"
                                                    variant={target === 'suspended' || target === 'unverified' ? 'outline' : 'default'}
                                                    onClick={() => move(c.id, target)}
                                                >
                                                    {actionLabel(c.status, target)}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            <Pagination data={contractors} />
                        </div>
                    )}
                    {errors.user_email && <p className="mt-3 text-xs font-semibold text-rose-600">{errors.user_email}</p>}
                </div>
            </div>
        </AdminLayout>
    );
}
